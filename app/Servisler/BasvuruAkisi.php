<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Notifications\BasvuruAlindi;
use App\Notifications\BasvuruOnaylandi;
use App\Notifications\BasvuruReddedildi;
use App\Notifications\EksikEvrakTalebi;
use App\Notifications\KurumTeyidiIstendi;
use Illuminate\Database\Eloquent\Collection;
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
    /** Kararı geri alınabilecek durumlar -- hepsi bir karar sonucudur. */
    private const GERI_ALINABILIR = [
        BasvuruDurumu::Onaylandi,
        BasvuruDurumu::Reddedildi,
        BasvuruDurumu::IptalEdildi,
    ];

    public function __construct(
        private DenetimYazici $denetim,
        private AkreditasyonAkisi $akreditasyon,
        private HesapAcici $hesapAcici,
        private BasvuruBiletiAkisi $bilet,
        private BasvuruNoUretici $basvuruNo,
    ) {}

    public function gonder(Basvuru $basvuru): void
    {
        $this->eksikZorunluEvrakVarsaDurdur($basvuru);

        /*
         * 🔑 DÜZELTMEDEN DÖNÜŞ ayrı bir duraktır (Cüneyt Bey revizyonu
         * 03.09.2026). Eskiden "Gönderildi"ye geri dönülüyordu ve kuyrukta
         * hiç açılmamış başvuruyla, bir kez incelenip belge istenmiş ve
         * cevabı gelmiş başvuru birbirinden ayırt edilemiyordu.
         */
        $duzeltmedenDonus = $basvuru->durum === BasvuruDurumu::EksikEvrak;

        /*
         * 🔑 Numara GÖNDERİM anında verilir, taslakta değil (saha notları T3):
         * gönderilmeyen başvuru numara yakarsa seride boşluk oluşur ve
         * "2026-BV-0137" sıralı olma iddiasını kaybeder. Düzeltmeden dönüşte
         * numara DEĞİŞMEZ -- başvuranın elindeki numara aynı başvuruyu
         * göstermeye devam etmeli.
         */
        $this->basvuruNo->ver($basvuru);

        // Teyit gerekliliği GÖNDERİM ANINDA dondurulur; ayar sonradan değişse de
        // yoldaki başvurunun kuralı değişmez.
        $teyitGerekli = $basvuru->kurum_teyidi === null
            && $this->kurumTeyidiGerekliMi($basvuru);

        $this->gecir(
            $basvuru,
            $duzeltmedenDonus ? BasvuruDurumu::YenidenInceleme : BasvuruDurumu::Gonderildi,
            $duzeltmedenDonus ? 'basvuru.yeniden_gonderildi' : 'basvuru.gonderildi',
            [
                'gonderildi_at' => now(),
                'duzeltme_notlari' => null,   // düzeltme tamamlandı
                'kurum_teyidi_gerekli' => $teyitGerekli,
            ],
        );

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

        if (! ($basvuru->kurum->teyit_istensin ?? Ayar::al('kurum_teyidi_istensin', false))) {
            return false;
        }

        /*
         * 💀 M9 №5: ULAŞILAMAYAN KURUM = KAYIP BAŞVURU.
         *
         * Teyit isteği yalnızca AKTİF kurum yetkililerine gidiyordu. Kurumun
         * tek yetkilisinin hesabı pasife alınmışsa hiçbir bildirim gitmiyor,
         * başvuru da `scopeKuyrukta()` dışında kaldığı için kulübün listesinde
         * HİÇ GÖRÜNMÜYORDU. Kimse beklediğini bilmiyor, kimse fark etmiyor:
         * başvuru sessizce kayboluyordu.
         *
         * 🔑 Cevaplayacak kimse yoksa TEYİT İSTENMEZ; başvuru doğrudan kulüp
         * kuyruğuna düşer. Teyit bir kolaylık, kapı değil -- kararı zaten kulüp
         * veriyor. Atlama denetime yazılır ki "bu başvuruda neden teyit
         * sorulmadı" sorusunun cevabı olsun.
         */
        if ($this->teyitHedefleri($basvuru)->isEmpty()) {
            $this->denetim->yaz('basvuru.kurum_teyidi_atlandi', $basvuru,
                yeni: ['kurum_id' => $basvuru->kurum_id],
                not: 'Kurumun teyit verebilecek aktif yetkilisi yok; başvuru doğrudan kuyruğa alındı.',
                aktorTip: 'sistem');

            return false;
        }

        return true;
    }

    /**
     * Teyidi verebilecek kişiler. Boşsa teyit istenmez (bkz. yukarısı).
     *
     * @return Collection<int, User>
     */
    private function teyitHedefleri(Basvuru $basvuru): Collection
    {
        return $basvuru->kurum
            ?->calisanlar()
            ->role(User::ROL_KURUM)
            ->where('aktif', true)
            ->whereNull('ayrildi_at')
            ->get()
            ?? new Collection;
    }

    private function kurumYetkililerineHaberVer(Basvuru $basvuru): void
    {
        $this->teyitHedefleri($basvuru)->each->notify(new KurumTeyidiIstendi($basvuru));
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

    /**
     * Kararı geri alır -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 💀 Onaylandı / Reddedildi / İptal edildi BİTİŞ durumuydu: yanlış karar
     * verildiğinde tek çıkış veritabanına elle müdahaleydi.
     *
     * 🔑 Bu bir DURUM DEĞİŞİKLİĞİ DEĞİL, kararın SONUÇLARINI toplamaktır.
     * Onay üç şey birden yapar (kart üretir, hesap açar, rol verir; kurumsalda
     * kurumu akredite eder). Yalnızca durumu çevirmek kartı turnikede geçerli,
     * hesabı panelde açık bırakırdı -- en tehlikeli yarım iş. Hepsi tek işlemde
     * geri alınır.
     *
     * ⚠️ HESAP SİLİNMEZ. Kişinin geçmişi (başvuruları, denetim izi, geçiş
     * kayıtları) ona bağlı; silmek o izi koparır. Yapılan şey erişimi
     * kapatmaktır: akreditasyon rolü alınır, başka bir dayanağı kalmadıysa
     * hesap pasife çekilir. Yetkili kararını düzeltip yeniden onaylarsa
     * `HesapAcici` aynı hesabı yeniden kullanır.
     */
    public function karariGeriAl(Basvuru $basvuru, string $gerekce): void
    {
        if (! in_array($basvuru->durum, self::GERI_ALINABILIR, true)) {
            throw new RuntimeException('Yalnızca karara bağlanmış başvurunun kararı geri alınabilir.');
        }

        $onceki = $basvuru->durum;

        DB::transaction(function () use ($basvuru, $gerekce, $onceki) {
            // 1) Kartlar: turnike erişimi ANINDA kapanmalı.
            /** @var Akreditasyon $akreditasyon */
            foreach ($basvuru->akreditasyon()->get() as $akreditasyon) {
                $this->akreditasyon->iptalEt(
                    $akreditasyon,
                    'Başvuru kararı geri alındı — '.$gerekce,
                );
            }

            // 2) Kurumsal onay kurumu akredite etmişti; o da geri alınır.
            if ($basvuru->tur === BasvuruTuru::Kurum
                && $basvuru->kurum
                && $basvuru->kurum->akreditasyon_durumu === 'akredite') {
                $basvuru->kurum->update(['akreditasyon_durumu' => 'beklemede']);
            }

            // 3) Onayla verilen bireysel rol geri alınır; hesap kalır.
            $this->erisimiKapat($basvuru);

            // 4) Durum: yeniden karar verilebilsin diye İnceleniyor.
            $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.karar_geri_alindi', [
                'karar_at' => null,
                'karar_veren_id' => null,
                'karar_gerekcesi' => null,
                'incelemeye_alindi_at' => now(),
                'inceleyen_id' => Auth::id(),
            ]);

            $this->denetim->yaz('basvuru.karar_geri_alindi', $basvuru,
                eski: ['durum' => $onceki->value],
                yeni: ['durum' => BasvuruDurumu::Incelemede->value],
                not: $gerekce);
        });
    }

    /** Karar geri alınırken kişinin akreditasyon erişimini kapatır. */
    private function erisimiKapat(Basvuru $basvuru): void
    {
        $kullanici = $basvuru->kullanici;

        if ($kullanici === null || $basvuru->tur === BasvuruTuru::Kurum) {
            return;
        }

        $kullanici->removeRole($basvuru->tur === BasvuruTuru::BasinMensubu
            ? User::ROL_BASIN
            : User::ROL_ICERIK);

        /*
         * Hesap yalnızca DAYANAĞI KALMADIYSA pasife alınır: kişi aynı zamanda
         * kurum yetkilisi olabilir ya da başka bir aktif akreditasyonu
         * bulunabilir. Onları da kapatmak, ilgisiz bir erişimi koparmak olurdu.
         */
        $baskaDayanak = $kullanici->fresh()->roles()->exists()
            || $kullanici->akreditasyonlar()
                ->where('durum', '!=', AkreditasyonDurumu::Iptal->value)
                ->exists();

        if (! $baskaDayanak) {
            $kullanici->forceFill(['aktif' => false])->save();
        }
    }

    public function incelemeyeAl(Basvuru $basvuru): void
    {
        $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi', [
            'incelemeye_alindi_at' => now(),
            'inceleyen_id' => Auth::id(),
        ]);
    }

    /**
     * Eksik/hatalı bilgi talebi. Her çağrı YENİ BİR TUR açar.
     *
     * 💀 Eskiden yalnızca `duzeltme_notlari` üzerine yazılıyordu: ikinci tur
     * birincinin üstünü siliyor, "ne istenmişti, ne değişti" sorusunun cevabı
     * hiçbir yerde kalmıyordu (Yusuf revizyonu 25.08.2026).
     *
     * @param  array<string, string>  $notlar  alan anahtarı => açıklama
     * @param  array<int, array<string, string>>  $ekTalepler  listemizde olmayan istekler
     */
    public function eksikEvrakIste(Basvuru $basvuru, array $notlar, ?string $mesaj = null, array $ekTalepler = []): BasvuruDuzeltmesi
    {
        if ($notlar === [] && $ekTalepler === []) {
            throw new RuntimeException('En az bir alan işaretlenmeli.');
        }

        // Ek talepler de işaretli alan sayılır: başvuran onları da doldurmalı.
        foreach ($ekTalepler as $ek) {
            $notlar[$ek['anahtar']] = $ek['aciklama'] ?? '';
        }

        $duzeltme = DB::transaction(function () use ($basvuru, $notlar, $mesaj, $ekTalepler) {
            $this->gecir($basvuru, BasvuruDurumu::EksikEvrak, 'basvuru.eksik_evrak', [
                'duzeltme_notlari' => $notlar,
                'karar_gerekcesi' => $mesaj,
            ]);

            return BasvuruDuzeltmesi::create([
                'basvuru_id' => $basvuru->id,
                'sira' => (int) $basvuru->duzeltmeler()->max('sira') + 1,
                'talep_notlari' => $notlar,
                'ek_talepler' => $ekTalepler ?: null,
                'talep_gerekcesi' => $mesaj,
                'talep_eden_id' => Auth::id(),
                'talep_at' => now(),
            ]);
        });

        // Panelsiz düzeltme bağlantısı: başvuranın hesabı olmayabilir.
        $token = $this->bilet->uret($basvuru);

        $basvuru->bildirimHedefi()->notify(new EksikEvrakTalebi($basvuru, $token));

        return $duzeltme;
    }

    /**
     * Başvuranın bu turda yaptıklarını KAYDEDER; turu KAPATMAZ.
     *
     * 💀 İkisi tek adımdı ve bu bir tuzaktı: zorunlu evrak hâlâ eksikse
     * `gonder()` patlıyor, başvuran aynı bağlantıya geri dönüyor ama tur
     * "yanıtlandı" işaretlendiği için `acikDuzeltme()` null oluyordu --
     * sayfadan tur başlığı ve EK TALEPLER kayboluyordu.
     *
     * Değişiklikler BİRİKİR: kişi iki denemede iki alanı düzeltebilir.
     *
     * @param  array<string, array{eski: mixed, yeni: mixed}>  $degisiklikler
     */
    public function duzeltmeyiKaydet(BasvuruDuzeltmesi $duzeltme, array $degisiklikler, ?string $aciklama): void
    {
        // 🪤 Birikimde ESKİ değer korunur: aynı alan ikinci kez düzeltilirse
        // "öncesi" ilk hâli olmalı, bir önceki denemenin hâli değil.
        $birikmis = $duzeltme->degisiklikler ?? [];

        foreach ($degisiklikler as $anahtar => $degisim) {
            $birikmis[$anahtar] = [
                'eski' => $birikmis[$anahtar]['eski'] ?? $degisim['eski'],
                'yeni' => $degisim['yeni'],
            ];
        }

        $duzeltme->forceFill([
            'degisiklikler' => $birikmis ?: null,
            'yanit_aciklama' => $aciklama ?: $duzeltme->yanit_aciklama,
        ])->save();
    }

    /** Tur kapanır: başvuru yeniden gönderildi. */
    public function duzeltmeyiKapat(BasvuruDuzeltmesi $duzeltme): void
    {
        $duzeltme->forceFill(['yanit_at' => now()])->save();

        $this->denetim->yaz('basvuru.duzeltme_yanitlandi', $duzeltme->basvuru,
            yeni: [
                'tur' => $duzeltme->sira,
                'degisen_alanlar' => array_keys($duzeltme->degisiklikler ?? []),
            ],
            aktorTip: 'sistem');
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
        $this->kontenjaniDogrula($basvuru);

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

    /**
     * Başvuruyu düşürür -- Cüneyt Bey revizyonu (03.09.2026), "İptal edildi".
     *
     * 🔑 RED DEĞİLDİR: red bir karardır, başvurana gerekçesiyle bildirilir ve
     * yeniden başvuru bekleme süresini işletir. İptal ise kaydın kapatılması
     * (mükerrer başvuru, başvuranın telefonla vazgeçmesi, yanlış türde
     * başvuru). Bu yüzden `karar_veren_id` DEĞİL yalnızca `karar_at` yazılır
     * ve başvurana bildirim gitmez.
     */
    public function iptalEt(Basvuru $basvuru, string $gerekce): void
    {
        DB::transaction(function () use ($basvuru, $gerekce) {
            $this->gecir($basvuru, BasvuruDurumu::IptalEdildi, 'basvuru.iptal_edildi', [
                'karar_at' => now(),
                'karar_gerekcesi' => $gerekce,
            ]);

            $this->kurumDurumunuSenkronla($basvuru, 'iptal_edildi');
        });
    }

    public function reddet(Basvuru $basvuru, string $gerekce): void
    {
        // İki geçiş TEK işlemde: ikincisi patlarsa başvuru "İncelemede" diye
        // asılı kalmasın.
        DB::transaction(function () use ($basvuru, $gerekce) {
            // Kurum teyidi reddi başvuruyu doğrudan düşürür; önce incelemeye
            // alınması beklenmez (md.5.2 "Başvuru düşer").
            if (in_array($basvuru->durum, BasvuruDurumu::acilmamis(), true)) {
                $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi', [
                    'incelemeye_alindi_at' => now(),
                ]);
            }

            $this->gecir($basvuru, BasvuruDurumu::Reddedildi, 'basvuru.reddedildi', [
                'karar_at' => now(),
                'karar_veren_id' => Auth::id(),
                'karar_gerekcesi' => $gerekce,
            ]);

            $this->kurumDurumunuSenkronla($basvuru, 'reddedildi');
        });

        $basvuru->bildirimHedefi()->notify(new BasvuruReddedildi($basvuru));
    }

    /**
     * Kurumsal başvurunun kararını KURUM kaydına da yazar (M1-A).
     *
     * 🔑 Kurum satırı başvuru anında `beklemede` doğar ve onayda `akredite`
     * olurdu; red ve iptal kuruma HİÇ DOKUNMUYORDU. Sonuç: reddedilen kurum
     * Kurumlar listesinde sonsuza kadar "Beklemede" görünüyor, Başvurular
     * ekranında ise varsayılan kuyruk süzgeci onu gizliyordu -- kullanıcının
     * "kurumlarda var, başvurularda yok" dediği tablo tam olarak buydu.
     *
     * ⚠️ YALNIZCA `beklemede` taşınır. Zaten akredite bir kurumun SONRAKİ bir
     * başvurusu reddedilirse akreditasyonu düşmemeli -- akreditasyon kaldırma
     * ayrı ve bilinçli bir eylemdir (KurumAkreditasyonu, durumu `iptal` yapar).
     * Bu yüzden buradaki durumlar `iptal`den de ayrı tutuldu: `iptal` "geri
     * ver" eylemini açar, `reddedildi`/`iptal_edildi` açmamalı.
     */
    private function kurumDurumunuSenkronla(Basvuru $basvuru, string $durum): void
    {
        if ($basvuru->tur !== BasvuruTuru::Kurum || ! $basvuru->kurum) {
            return;
        }

        $kurum = $basvuru->kurum;

        if ($kurum->akreditasyon_durumu !== 'beklemede') {
            return;
        }

        $kurum->update(['akreditasyon_durumu' => $durum]);

        $this->denetim->yaz('kurum.durum_degisti', $kurum,
            eski: ['akreditasyon_durumu' => 'beklemede'],
            yeni: ['akreditasyon_durumu' => $durum],
            not: "Başvuru {$basvuru->basvuru_no} kararıyla eşitlendi");
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

    /**
     * Kurum kontenjanı -- Tutarsızlık incelemesi M9 №6.
     *
     * 💀 `Kurum::kontenjanDoldu()` VARDI ama hiçbir yerden çağrılmıyordu:
     * başvuru alınıyor, onayda da engel çıkmıyor, kontenjan sessizce
     * aşılıyordu. 10 kişilik kontenjanı olan kurumdan 12 kart çıkabiliyordu;
     * panodaki "kontenjanı dolan kurum" kutusu da olan biteni ancak İŞ
     * OLDUKTAN SONRA gösteriyordu.
     *
     * 🔑 Kontrol ONAY anında: kontenjanı tüketen şey başvuru değil, üretilen
     * KART. İşlemin dışında ve başında -- yetkili "onayla" der demez sebebi
     * öğrensin, yarım kalmış bir işlem geri sarılmasın.
     *
     * ⚠️ Sert engel bilerek: kontenjan Kurumlar ekranından değiştirilebiliyor
     * (KurumFormu). Kararı kulüp verir; sistem sessizce aşmaz.
     */
    private function kontenjaniDogrula(Basvuru $basvuru): void
    {
        // Kurumsal onayda kart çıkmaz; kontenjan çalışanların kartları içindir.
        if ($basvuru->tur === BasvuruTuru::Kurum || ! $basvuru->kurum) {
            return;
        }

        // Yeniden onayda yeni kart doğmaz (AkreditasyonAkisi mükerrer üretmez),
        // yani kontenjandan da bir şey eksilmez.
        if ($basvuru->akreditasyon()->exists()) {
            return;
        }

        if (! $basvuru->kurum->kontenjanDoldu()) {
            return;
        }

        throw new RuntimeException(
            $basvuru->kurum->resmi_unvan.' kontenjanı dolu ('
            .$basvuru->kurum->kontenjan.' kart). Onaylamak için Kurumlar '
            .'ekranından kontenjanı artırın ya da bir kartı iptal edin.',
        );
    }

    private function eksikZorunluEvrakVarsaDurdur(Basvuru $basvuru): void
    {
        /*
         * ⚠️ `where('zorunlu', true)` DEĞİL: yeni bir belge zorunlu yapıldığında
         * kuyrukta bekleyen başvurular kilitlenmemeli (bkz.
         * EvrakTuru::basvuruIcinZorunluMu -- M7.2 mimari notu).
         */
        $gereken = EvrakTuru::turIcin($basvuru->tur)
            ->filter(fn (EvrakTuru $t) => $t->basvuruIcinZorunluMu($basvuru));
        $yuklenen = $basvuru->evraklar()->pluck('evrak_turu_id')->all();

        $eksik = $gereken->reject(fn ($t) => in_array($t->id, $yuklenen, true));

        if ($eksik->isNotEmpty()) {
            throw new RuntimeException('Eksik zorunlu evrak: '.$eksik->pluck('ad')->implode(', '));
        }
    }
}
