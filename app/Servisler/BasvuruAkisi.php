<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\DuzeltmeTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Notifications\BasvuruAlindi;
use App\Notifications\BasvuruOnaylandi;
use App\Notifications\BasvuruReddedildi;
use App\Notifications\BelgeTalebi;
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
    /**
     * Belge talebinin varsayılan süresi (gün).
     *
     * ⚠️ BİLGİ AMAÇLI. Süre dolduğunda sistem kartı askıya almaz, erişimi
     * kesmez, talebi kapatmaz: kayıt panoda ve akreditasyon detayında "süresi
     * geçti" diye görünür, kararı yetkili verir.
     */
    public const BELGE_TALEBI_GUN = 7;

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
        private KurumAkreditasyonu $kurumAkreditasyonu,
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
                // 🔑 Kulüp kararı DEĞİL: karar veren olarak kurum çalışanı yazılmasın.
                $this->reddet(
                    $basvuru,
                    $not ?: 'Kurum, başvuranın çalışanı olduğunu teyit etmedi.',
                    kulupKarari: false,
                );
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
    public function karariGeriAl(Basvuru $basvuru, string $gerekce, bool $kartlariAskiyaAl = false): void
    {
        if (! in_array($basvuru->durum, self::GERI_ALINABILIR, true)) {
            throw new RuntimeException('Yalnızca karara bağlanmış başvurunun kararı geri alınabilir.');
        }

        $onceki = $basvuru->durum;

        DB::transaction(function () use ($basvuru, $gerekce, $onceki, $kartlariAskiyaAl) {
            // 1) Bu başvurunun kartları: turnike erişimi ANINDA kapanmalı.
            /** @var Akreditasyon $akreditasyon */
            foreach ($basvuru->akreditasyon()->get() as $akreditasyon) {
                $this->akreditasyon->iptalEt(
                    $akreditasyon,
                    'Başvuru kararı geri alındı — '.$gerekce,
                );
            }

            // 2) Kararın KURUM kaydına yazdığı sonuç da geri alınır.
            $oncekiKurumDurumu = $this->kurumKararindanGeriAl($basvuru);

            /*
             * 3) ÇALIŞANLARIN KARTLARI -- M9 №1'in aynısı, öbür kapıda.
             *
             * 💀 Kurumun akreditasyonu iki yoldan düşüyor: "Akreditasyonu
             * kaldır" ve buradaki "Kararı geri al". Birincisi kaç kartın
             * etkilendiğini sayıp yetkiliye söylüyor ve askıya almayı
             * öneriyordu; ikincisi kurumu sessizce `beklemede`ye çekip
             * ÇALIŞANLARIN KARTLARINI AKTİF BIRAKIYORDU. Akreditasyonu
             * düşmüş kuruluşun muhabiri turnikeden geçmeye devam ediyordu --
             * yetkili aynı işi yaptığını sanarak farklı bir sonuç alıyordu.
             *
             * Kartlar burada da otomatik İPTAL EDİLMEZ: iptal geri alınamaz
             * ve kişilerin kendi başvuruları olabilir. Yapılan şey saymak,
             * denetime yazmak ve istenirse ASKIYA almak -- askı dönülebilir.
             */
            $etkilenenKart = 0;

            if ($oncekiKurumDurumu === 'akredite' && $basvuru->kurum) {
                $etkilenenKart = $this->kurumAkreditasyonu->aktifKartSayisi($basvuru->kurum);

                if ($kartlariAskiyaAl && $etkilenenKart > 0) {
                    $this->kurumAkreditasyonu->aktifKartlariAskiyaAl(
                        $basvuru->kurum,
                        'Kurumun akreditasyon kararı geri alındı — '.$gerekce,
                    );
                }
            }

            // 4) Onayla verilen bireysel rol geri alınır; hesap kalır.
            $this->erisimiKapat($basvuru);

            // 5) Durum: yeniden karar verilebilsin diye İnceleniyor.
            $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.karar_geri_alindi', [
                'karar_at' => null,
                'karar_veren_id' => null,
                'karar_gerekcesi' => null,
                'incelemeye_alindi_at' => now(),
                'inceleyen_id' => Auth::id(),
            ]);

            $this->denetim->yaz('basvuru.karar_geri_alindi', $basvuru,
                eski: ['durum' => $onceki->value],
                yeni: [
                    'durum' => BasvuruDurumu::Incelemede->value,
                    // "Kaç kart etkilendi" sorusunun cevabı kayıtta dursun.
                    'etkilenen_aktif_kart' => $etkilenenKart,
                    'kartlar_askiya_alindi' => $kartlariAskiyaAl && $etkilenenKart > 0,
                ],
                not: $gerekce);
        });
    }

    /**
     * Kurumsal kararın KURUM satırına yazdığı sonucu geri sarar.
     * Dokunulduysa kurumun önceki durumunu, dokunulmadıysa null döndürür.
     *
     * 💀 Eskiden yalnızca `akredite` geri alınıyordu. Reddedilen kurumsal
     * başvurunun kararı geri alındığında kurum `reddedildi` KALIYORDU:
     * Kurumlar ekranı kırmızı "Reddedildi" derken Başvurular ekranı
     * "İnceleniyor" diyordu -- M1-A'da düzeltilen çelişkinin aynısı, bu kez
     * karar geri alma yolundan geri gelmişti. Üstelik kalıcıydı: başvuru
     * sonra iptal edilse `kurumDurumunuSenkronla()` kurumu `beklemede`
     * bulamadığı için erken dönüyor, kurum sonsuza kadar "Reddedildi"
     * görünüyordu.
     *
     * ⚠️ `iptal`e DOKUNULMAZ. O bir başvuru kararının sonucu değil,
     * "Akreditasyonu kaldır" ile verilmiş ayrı ve bilinçli bir karardır
     * (KurumAkreditasyonu). Buradan sıfırlansaydı kulübün kararı sessizce
     * silinir, üstelik yalnızca `iptal`de açılan "geri ver" eylemi de
     * kaybolurdu.
     */
    private function kurumKararindanGeriAl(Basvuru $basvuru): ?string
    {
        if ($basvuru->tur !== BasvuruTuru::Kurum || ! $basvuru->kurum) {
            return null;
        }

        $kurum = $basvuru->kurum;
        $onceki = $kurum->akreditasyon_durumu;

        // Yalnızca BAŞVURU KARARININ yazabildiği durumlar geri alınır.
        if (! in_array($onceki, ['akredite', 'reddedildi', 'iptal_edildi'], true)) {
            return null;
        }

        /*
         * 🪤 Kurumun BAŞKA bir onaylı kurumsal başvurusu varsa akreditasyon
         * ona dayanıyordur; eski bir kararı geri almak onu düşürmemeli.
         */
        $baskaOnay = $kurum->basvurular()
            ->whereKeyNot($basvuru->getKey())
            ->where('tur', BasvuruTuru::Kurum->value)
            ->where('durum', BasvuruDurumu::Onaylandi->value)
            ->exists();

        if ($baskaOnay) {
            return null;
        }

        $kurum->update(['akreditasyon_durumu' => 'beklemede']);

        $this->denetim->yaz('kurum.durum_degisti', $kurum,
            eski: ['akreditasyon_durumu' => $onceki],
            yeni: ['akreditasyon_durumu' => 'beklemede'],
            not: "Başvuru {$basvuru->basvuru_no} kararı geri alındı");

        return $onceki;
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
                'sira' => $this->sonrakiSira($basvuru),
                'tur' => DuzeltmeTuru::Duzeltme,
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
     * KARARA BAĞLANMIŞ başvuruya belge talebi -- Cüneyt Bey revizyonu
     * (05.09.2026).
     *
     * 💀 Akredite birinden tek bir belge istemenin yolu yoktu. `eksikEvrakIste`
     * başvuruyu `eksik_evrak`a düşürdüğü için policy onu yalnız "İnceleniyor"da
     * açıyor; yetkili de önce "Akreditasyonu geri al" demek zorunda kalıyordu.
     * O adım kartı GERİ ALINAMAZ biçimde iptal ediyor (`karariGeriAl` md.1),
     * rolü düşürüyor, paneli kapatıyor ve cevap gelince bütün onay turu baştan
     * işliyordu. Bir eksik fotoğraf için kart yakılıyordu.
     *
     * 🔑 Bu metot `gecir()` ÇAĞIRMAZ: başvuru `onaylandi` kalır, kart aktif
     * kalır, turnikeden geçiş kesilmez. Yapılan tek şey belge istemek.
     *
     * 🪤 `karar_gerekcesi` ALANINA DOKUNULMAZ -- orada ONAY gerekçesi duruyor;
     * `eksikEvrakIste` oraya yazabilir çünkü karar öncesi henüz boştur. Talebin
     * mesajı turun kendi `talep_gerekcesi` alanında saklanır.
     *
     * @param  array<string, string>  $notlar  alan anahtarı => açıklama
     * @param  array<int, array<string, string>>  $ekTalepler
     * @param  int  $sureGun  bilgi amaçlı süre; dolunca sistem HİÇBİR ŞEY yapmaz
     */
    public function belgeTalepEt(
        Basvuru $basvuru,
        array $notlar,
        ?string $mesaj = null,
        array $ekTalepler = [],
        int $sureGun = self::BELGE_TALEBI_GUN,
    ): BasvuruDuzeltmesi {
        if ($basvuru->durum !== BasvuruDurumu::Onaylandi) {
            throw new RuntimeException('Belge talebi yalnızca onaylanmış başvuruda açılır; '
                .'karar öncesi için "Belge iste" adımını kullanın.');
        }

        /*
         * 🪤 TEK AÇIK TUR. `duzeltilebilirAlanlar()` ve düzeltme formu
         * `basvurular.duzeltme_notlari` tek alanına bakıyor: ikinci bir tur
         * açılsaydı birincinin istediği kalemler sessizce silinirdi.
         */
        if ($basvuru->acikDuzeltme() !== null) {
            throw new RuntimeException('Bu başvuruda yanıtlanmamış bir talep zaten var; '
                .'önce o kapansın.');
        }

        if ($notlar === [] && $ekTalepler === []) {
            throw new RuntimeException('En az bir belge işaretlenmeli.');
        }

        foreach ($ekTalepler as $ek) {
            $notlar[$ek['anahtar']] = $ek['aciklama'] ?? '';
        }

        $sonTarih = now()->copy()->addDays(max(1, $sureGun))->startOfDay();

        $duzeltme = DB::transaction(function () use ($basvuru, $notlar, $mesaj, $ekTalepler, $sonTarih) {
            // Durum DEĞİŞMEDİĞİ için `gecir()` değil düz yazım; denetim elle.
            $basvuru->fill(['duzeltme_notlari' => $notlar])->save();

            $this->denetim->yaz('basvuru.belge_talebi', $basvuru, yeni: [
                'istenen' => array_keys($notlar),
                'son_tarih' => $sonTarih->toDateString(),
            ]);

            return BasvuruDuzeltmesi::create([
                'basvuru_id' => $basvuru->id,
                'sira' => $this->sonrakiSira($basvuru),
                'tur' => DuzeltmeTuru::BelgeTalebi,
                'talep_notlari' => $notlar,
                'ek_talepler' => $ekTalepler ?: null,
                'talep_gerekcesi' => $mesaj,
                'talep_eden_id' => Auth::id(),
                'talep_at' => now(),
                'son_tarih' => $sonTarih,
            ]);
        });

        // Hesabı olan da olmayan da aynı bağlantıyı kullanır (Revizyon md.3.3).
        $token = $this->bilet->uret($basvuru, 'belge_talebi');

        $basvuru->bildirimHedefi()->notify(new BelgeTalebi($basvuru, $duzeltme, $token));

        return $duzeltme;
    }

    /**
     * Belge talebi turunu KAPATIR -- `gonder()`in belge talebi karşılığı.
     *
     * 🔑 `gonder()` burada ÇAĞRILAMAZ: o metot başvuruyu `yeniden_inceleme`ye
     * sokar, zorunlu evrak denetimini baştan çalıştırır ve yeni bir karar
     * bekler. Onaylanmış bir başvuruda istenen şey bu değil -- belge geldi,
     * dosyaya girdi, iş bitti. Kart ve durum aynı kalır.
     *
     * @param  array<string, array{eski: mixed, yeni: mixed}>  $degisiklikler
     */
    public function belgeTalebiniKapat(BasvuruDuzeltmesi $duzeltme, array $degisiklikler, ?string $aciklama): void
    {
        $this->duzeltmeyiKaydet($duzeltme, $degisiklikler, $aciklama);

        $basvuru = $duzeltme->basvuru;

        DB::transaction(function () use ($basvuru, $duzeltme, $degisiklikler) {
            // Açık tur işareti kalkar; yoksa panelde "belge bekleniyor" şeridi
            // sonsuza kadar durur (`gonder()` bunu kendi yapıyordu).
            $basvuru->fill(['duzeltme_notlari' => null])->save();

            $this->denetim->yaz('basvuru.belge_talebi_yanitlandi', $basvuru, yeni: [
                'tur' => $duzeltme->sira,
                'gelen' => array_keys($degisiklikler),
            ], aktorTip: 'sistem');
        });

        $this->duzeltmeyiKapat($duzeltme);
    }

    /**
     * Tur numarası başvuru genelinde TEK seridir: düzeltme ve belge talebi
     * ayrı sayılsaydı `(basvuru_id, sira)` benzersiz indeksi çakışırdı.
     */
    private function sonrakiSira(Basvuru $basvuru): int
    {
        return (int) $basvuru->duzeltmeler()->max('sira') + 1;
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

    /**
     * @param  bool  $kulupKarari  Kararı KULÜP mü verdi? Kurum teyidi reddinde
     *                             false: oturumdaki kişi kurum çalışanıdır.
     *
     * 💀 `karar_veren_id` HER ZAMAN `Auth::id()` yazılıyordu. Kurum panelinden
     * "bu kişi çalışanımız değil" denince bu metot kurum çalışanının
     * oturumunda çalışıyor ve KURUM ÇALIŞANI kulübün karar vereni olarak
     * kaydediliyordu: CSV'deki "Karar veren" sütununda kulübün raporunda bir
     * kurum personeli görünüyordu. Kurumun "hayır"ı başvuruyu düşürür ama
     * kulübün kararı değildir -- tıpkı iptalde olduğu gibi kişi yazılmaz.
     */
    public function reddet(Basvuru $basvuru, string $gerekce, bool $kulupKarari = true): void
    {
        // İki geçiş TEK işlemde: ikincisi patlarsa başvuru "İncelemede" diye
        // asılı kalmasın.
        DB::transaction(function () use ($basvuru, $gerekce, $kulupKarari) {
            // Kurum teyidi reddi başvuruyu doğrudan düşürür; önce incelemeye
            // alınması beklenmez (md.5.2 "Başvuru düşer").
            if (in_array($basvuru->durum, BasvuruDurumu::acilmamis(), true)) {
                /*
                 * 🪤 SAAT YAZILMAZ (kulüp kararı değilse). Durum makinesi
                 * Gönderildi → Reddedildi geçişine izin vermiyor, buradan
                 * geçmek zorunlu -- ama bu bir inceleme DEĞİL, kimse başvuruyu
                 * açmadı. Saat yazılınca ekran "Sorumlu: (boş)" derken
                 * "İncelemeye alındı 06:27" diyor ve yetkili bir
                 * meslektaşının açtığını sanıyordu.
                 */
                $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi',
                    $kulupKarari ? ['incelemeye_alindi_at' => now()] : []);
            }

            $this->gecir($basvuru, BasvuruDurumu::Reddedildi, 'basvuru.reddedildi', [
                'karar_at' => now(),
                'karar_veren_id' => $kulupKarari ? Auth::id() : null,
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
