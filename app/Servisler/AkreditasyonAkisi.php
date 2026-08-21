<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\User;
use App\Jobs\KartUret;
use App\Notifications\AkreditasyonDurumuDegisti;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Akreditasyon yaşam döngüsü -- Plan v1.0 md.4, md.5.4.
 *
 * 🔑 Yetki bilgisi kartta değil BURADA. İptal/askı anında turnikede etkilidir.
 * ⏳ Kart PDF/QR üretimi 04. aşamada; bu servis akreditasyon KAYDINI yönetir.
 */
class AkreditasyonAkisi
{
    public function __construct(
        private DenetimYazici $denetim,
        private KartNoUretici $kartNo,
    ) {}

    /** Onaylanan bireysel başvurudan akreditasyon doğar. */
    public function basvurudanOlustur(Basvuru $basvuru): ?Akreditasyon
    {
        if ($basvuru->tur === BasvuruTuru::Kurum) {
            return null;   // Kurumsal onay = kurumun akredite olması, kart çıkmaz.
        }

        if ($basvuru->akreditasyon()->exists()) {
            return $basvuru->akreditasyon;   // yeniden onay: mükerrer kart üretme
        }

        return DB::transaction(function () use ($basvuru) {
            $akreditasyon = $this->kartNo->uret(
                $basvuru->tur,
                fn (array $numara) => Akreditasyon::create($numara + [
                    'kullanici_id' => $basvuru->kullanici_id,
                    'basvuru_id'   => $basvuru->id,
                    'kurum_id'     => $basvuru->kurum_id,
                    'durum'        => AkreditasyonDurumu::Aktif,
                    // Sezon/geçerlilik Faz 2 — alanlar boş bırakılıyor (md.4).
                ]),
            );

            $basvuru->kullanici->syncRoles([
                $basvuru->tur === BasvuruTuru::BasinMensubu ? User::ROL_BASIN : User::ROL_ICERIK,
            ]);

            $this->denetim->yaz('akreditasyon.olusturuldu', $akreditasyon, yeni: [
                'kart_no' => $akreditasyon->kart_no,
            ]);

            // Kart üretimi kuyrukta: başsız Chrome birkaç saniye sürüyor,
            // yetkili onay düğmesine basınca ekran beklemesin.
            // afterCommit: iş, kayıt gerçekten yazılmadan başlamasın.
            KartUret::dispatch($akreditasyon)->afterCommit();

            return $akreditasyon;
        });
    }

    public function askiyaAl(Akreditasyon $akreditasyon, string $gerekce): void
    {
        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Askida, 'akreditasyon.askiya_alindi', $gerekce, [
            'askiya_alindi_at' => now(),
        ]);
    }

    public function yenidenAktiflestir(Akreditasyon $akreditasyon): void
    {
        if ($akreditasyon->durum === AkreditasyonDurumu::Iptal) {
            throw new RuntimeException('İptal edilmiş akreditasyon yeniden aktifleştirilemez; yeni başvuru gerekir.');
        }

        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Aktif, 'akreditasyon.yeniden_aktif', null, [
            'askiya_alindi_at' => null,
        ]);
    }

    /**
     * İptal. Ayrılış bildiriminde OTOMATİK çağrılır (md.5.4) — turnike erişimi
     * anında kapanır, kart geri toplanmaz.
     */
    public function iptalEt(Akreditasyon $akreditasyon, string $neden, string $aktorTip = 'kullanici'): void
    {
        if ($akreditasyon->durum === AkreditasyonDurumu::Iptal) {
            return;   // zaten iptal; mükerrer bildirim gönderme
        }

        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Iptal, 'akreditasyon.iptal', $neden, [
            'iptal_at'      => now(),
            'iptal_nedeni'  => $neden,
        ], $aktorTip);
    }

    private function durumaGec(
        Akreditasyon $akreditasyon,
        AkreditasyonDurumu $hedef,
        string $olay,
        ?string $gerekce,
        array $alanlar = [],
        string $aktorTip = 'kullanici',
    ): void {
        DB::transaction(function () use ($akreditasyon, $hedef, $olay, $gerekce, $alanlar, $aktorTip) {
            $eski = $akreditasyon->durum;

            $akreditasyon->fill($alanlar + [
                'durum'               => $hedef,
                'durum_degistiren_id' => Auth::id(),
            ])->save();

            $this->denetim->yaz($olay, $akreditasyon,
                eski: ['durum' => $eski->value],
                yeni: ['durum' => $hedef->value],
                not: $gerekce,
                aktorTip: $aktorTip);
        });

        $akreditasyon->kullanici?->notify(new AkreditasyonDurumuDegisti($akreditasyon, $gerekce));
    }
}
