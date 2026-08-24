<?php

namespace App\Servisler;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Notifications\BasvuruAlindi;
use App\Notifications\BasvuruOnaylandi;
use App\Notifications\BasvuruReddedildi;
use App\Notifications\EksikEvrakTalebi;
use App\Notifications\KurumTeyidiIstendi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Başvuru durum akışı -- Plan v1.0 md.4.
 *
 * 🔑 Durum değişikliği YALNIZCA buradan geçer. Ekranlar doğrudan
 * `$basvuru->durum = ...` yazmaz; böylece her geçiş denetim kaydına düşer,
 * bildirim atlanmaz ve geçersiz geçiş sessizce olmaz.
 */
class BasvuruAkisi
{
    public function __construct(
        private DenetimYazici $denetim,
        private AkreditasyonAkisi $akreditasyon,
        private HesapAcici $hesapAcici,
        private BasvuruBiletiAkisi $bilet,
    ) {}

    public function gonder(Basvuru $basvuru): void
    {
        $this->eksikZorunluEvrakVarsaDurdur($basvuru);

        // Teyit gerekliliği GÖNDERİM ANINDA dondurulur; ayar sonradan değişse de
        // yoldaki başvurunun kuralı değişmez.
        $teyitGerekli = $basvuru->kurum_teyidi === null
            && $this->kurumTeyidiGerekliMi($basvuru);

        $this->gecir($basvuru, BasvuruDurumu::Gonderildi, 'basvuru.gonderildi', [
            'gonderildi_at' => now(),
            'duzeltme_notlari' => null,   // düzeltme tamamlandı
            'kurum_teyidi_gerekli' => $teyitGerekli,
        ]);

        $basvuru->bildirimHedefi()->notify(new BasvuruAlindi($basvuru));

        if ($teyitGerekli) {
            $this->kurumYetkililerineHaberVer($basvuru);
        }
    }

    /**
     * Kurum teyidi -- Plan v1.0 md.5.2.
     * Yalnızca basın mensubu başvurusunda ve KİŞİ KENDİSİ başvurduysa istenir;
     * kurum kendi başlattıysa (Yol B) ikinci bir teyide gerek yok.
     * Kurum kaydındaki ayar sistemi ezer (null ise sistem ayarı geçerli).
     */
    private function kurumTeyidiGerekliMi(Basvuru $basvuru): bool
    {
        if ($basvuru->tur !== BasvuruTuru::BasinMensubu || $basvuru->kurum === null) {
            return false;
        }

        if ($basvuru->kurum_baslatti) {
            return false;
        }

        return (bool) ($basvuru->kurum->teyit_istensin ?? Ayar::al('kurum_teyidi_istensin', false));
    }

    private function kurumYetkililerineHaberVer(Basvuru $basvuru): void
    {
        $basvuru->kurum
            ?->calisanlar()
            ->role(User::ROL_KURUM)
            ->where('aktif', true)
            ->get()
            ->each
            ->notify(new KurumTeyidiIstendi($basvuru));
    }

    /**
     * Kurumun cevabı. "Hayır" derse başvuru DÜŞER (md.5.2 akış şeması) —
     * yetkili kuyruğuna hiç girmez.
     */
    public function kurumTeyidiVer(Basvuru $basvuru, bool $onay, ?string $not = null): void
    {
        if (! $basvuru->kurumTeyidiBekliyorMu()) {
            throw new RuntimeException('Bu başvuru kurum teyidi beklemiyor.');
        }

        DB::transaction(function () use ($basvuru, $onay, $not) {
            $basvuru->fill([
                'kurum_teyidi' => $onay,
                'kurum_teyidi_at' => now(),
            ])->save();

            $this->denetim->yaz($onay ? 'basvuru.kurum_teyidi_verildi' : 'basvuru.kurum_teyidi_reddedildi',
                $basvuru, yeni: ['kurum_teyidi' => $onay], not: $not);

            /*
             * 💀 Red AYNI işlemde olmalı. Ayrı yazıldığında teyit "hayır"
             * olarak kaydedilip red yazılamazsa başvuru `kurum_teyidi`
             * dolu olduğu için scopeKuyrukta()'ya DÜŞÜYORDU: kurum
             * reddetmiş ama yetkili kuyruğunda onaylanmış gibi duruyordu.
             */
            if (! $onay) {
                $this->reddet($basvuru, $not ?: 'Kurum, başvuranın çalışanı olduğunu teyit etmedi.');
            }
        });
    }

    public function incelemeyeAl(Basvuru $basvuru): void
    {
        $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi', [
            'incelemeye_alindi_at' => now(),
            'inceleyen_id' => Auth::id(),
        ]);
    }

    /** @param array<string, string> $notlar alan adı => açıklama */
    public function eksikEvrakIste(Basvuru $basvuru, array $notlar, ?string $mesaj = null): void
    {
        if ($notlar === []) {
            throw new RuntimeException('En az bir alan işaretlenmeli.');
        }

        $this->gecir($basvuru, BasvuruDurumu::EksikEvrak, 'basvuru.eksik_evrak', [
            'duzeltme_notlari' => $notlar,
            'karar_gerekcesi' => $mesaj,
        ]);

        // Panelsiz düzeltme bağlantısı: başvuranın hesabı olmayabilir.
        $token = $this->bilet->uret($basvuru);

        $basvuru->bildirimHedefi()->notify(new EksikEvrakTalebi($basvuru, $token));
    }

    /**
     * Onay -- durum, kurum akreditasyonu / kart kaydı ve roller TEK işlemde.
     *
     * 💀 Eskiden durum ayrı, sonuçları ayrı yazılıyordu: kart numarası
     * üretilemezse başvuru "Onaylandı" kalıyor ama akreditasyon doğmuyordu.
     * Onaylandı'nın sonraki durumu olmadığı için işlem TEKRARLANAMIYOR,
     * kayıt elle düzeltilmeden kurtarılamıyordu.
     */
    public function onayla(Basvuru $basvuru): void
    {
        [$kullanici, $sifreBelirlenecek] = DB::transaction(function () use ($basvuru) {
            $this->gecir($basvuru, BasvuruDurumu::Onaylandi, 'basvuru.onaylandi', [
                'karar_at' => now(),
                'karar_veren_id' => Auth::id(),
            ]);

            // ── HESAP BURADA AÇILIR (Revizyon md.3.2) ──
            // Rol ataması da HesapAcici'de: onaylanmayan kişinin hesabı da rolü
            // de hiç doğmaz.
            [$kullanici, $sifreBelirlenecek] = $this->hesapAcici->onaydanOlustur($basvuru);

            // Kurumsal başvuruda onay = kurumun AKREDİTE olması ve yetkilinin
            // kurum paneline açılması (Plan v1.0 md.5.1).
            if ($basvuru->tur === BasvuruTuru::Kurum && $basvuru->kurum) {
                $basvuru->kurum->update(['akreditasyon_durumu' => 'akredite']);
            } else {
                // Bireysel onay: akreditasyon kaydı ve kart numarası burada doğar.
                $this->akreditasyon->basvurudanOlustur($basvuru);
            }

            return [$kullanici, $sifreBelirlenecek];
        });

        // Bildirim hesabın KENDİSİNE gider: imzalı şifre bağlantısı kullanıcının
        // ulid'ini ister, anonim adres yetmez.
        $kullanici->notify(new BasvuruOnaylandi($basvuru, $sifreBelirlenecek));
    }

    public function reddet(Basvuru $basvuru, string $gerekce): void
    {
        // İki geçiş TEK işlemde: ikincisi patlarsa başvuru "İncelemede" diye
        // asılı kalmasın.
        DB::transaction(function () use ($basvuru, $gerekce) {
            // Kurum teyidi reddi başvuruyu doğrudan düşürür; önce incelemeye
            // alınması beklenmez (md.5.2 "Başvuru düşer").
            if ($basvuru->durum === BasvuruDurumu::Gonderildi) {
                $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi', [
                    'incelemeye_alindi_at' => now(),
                ]);
            }

            $this->gecir($basvuru, BasvuruDurumu::Reddedildi, 'basvuru.reddedildi', [
                'karar_at' => now(),
                'karar_veren_id' => Auth::id(),
                'karar_gerekcesi' => $gerekce,
            ]);
        });

        $basvuru->bildirimHedefi()->notify(new BasvuruReddedildi($basvuru));
    }

    /** Ortak geçiş: doğrula → yaz → denetle, hepsi tek işlemde. */
    private function gecir(Basvuru $basvuru, BasvuruDurumu $hedef, string $olay, array $alanlar = []): void
    {
        DB::transaction(function () use ($basvuru, $hedef, $olay, $alanlar) {
            $eski = ['durum' => $basvuru->durum->value];

            $basvuru->durumaGec($hedef);          // geçersizse fırlatır
            $basvuru->fill($alanlar)->save();

            $this->denetim->yaz($olay, $basvuru,
                eski: $eski,
                yeni: ['durum' => $hedef->value] + array_map(
                    fn ($v) => $v instanceof \DateTimeInterface ? $v->format('c') : $v,
                    $alanlar,
                ),
            );
        });
    }

    private function eksikZorunluEvrakVarsaDurdur(Basvuru $basvuru): void
    {
        $gereken = EvrakTuru::turIcin($basvuru->tur)->where('zorunlu', true);
        $yuklenen = $basvuru->evraklar()->pluck('evrak_turu_id')->all();

        $eksik = $gereken->reject(fn ($t) => in_array($t->id, $yuklenen, true));

        if ($eksik->isNotEmpty()) {
            throw new RuntimeException('Eksik zorunlu evrak: '.$eksik->pluck('ad')->implode(', '));
        }
    }
}
