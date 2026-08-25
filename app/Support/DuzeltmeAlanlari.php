<?php

namespace App\Support;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\EvrakTuru;

/**
 * Düzeltme talebinde işaretlenebilen alanların ANAHTAR ŞEMASI --
 * Düzeltme listesi md.11.
 *
 * 💀 Eskiden anahtar evrak türünün GÖRÜNEN ADIYDI (`"Kimlik / ehliyet /
 * pasaport"`). Yetkili panelden o adı değiştirdiğinde yolda olan biletlerde
 * yükleme kutusu SESSİZCE kayboluyordu: başvuran ne yükleyeceğini göremiyor,
 * gönderince "eksik zorunlu evrak" hatası alıyor ve çıkmaza giriyordu.
 *
 * 🔑 Anahtar artık değişmez bir koddur:
 *   - `evrak:<kod>` -- EvrakTuru.kod (yetkili adı değiştirse de kod sabit)
 *   - `veri:<alan>` -- başvuru formundaki veri alanı
 * Etiket her zaman anahtardan ÜRETİLİR, saklanmaz.
 *
 * 🪤 GERİYE DÖNÜK: yoldaki biletlerde çıplak ad anahtarları var. `etiket()`
 * tanımadığı anahtarı olduğu gibi döndürür, `evrakAnahtarlari()` ise eski
 * adı da kabul eder. Geçiş bitince o kabul kaldırılabilir.
 */
class DuzeltmeAlanlari
{
    public const EVRAK_ONEK = 'evrak:';

    public const VERI_ONEK = 'veri:';

    /** Alan listemizde OLMAYAN, yetkilinin elle tanımladığı talep. */
    public const EK_ONEK = 'ek:';

    /**
     * Veri alanlarının TANIMI -- Yusuf revizyonu 25.08.2026.
     *
     * 💀 Eskiden bu liste yalnızca ETİKETTEN ibaretti: yetkili "Telefon"u
     * işaretliyor ama başvurana yalnızca serbest bir açıklama kutusu
     * açılıyordu. Kişi doğrusunu yazıyor, başvuru yeniden gönderiliyor,
     * YANLIŞ VERİ HÂLÂ YANLIŞ kalıyordu.
     *
     * Her alan artık nerede saklandığını ve hangi girdiyle düzeltileceğini
     * de söyler:
     *   tip     -- ekranda çizilecek girdi
     *   kaynak  -- `basvuru:<sutun>` | `form:<anahtar>` | `kurum:<sutun>`
     *   duzeltilebilir -- false ise yalnızca GÖSTERİLİR, başvuran açıklama
     *                     kutusundan yanıt verir
     *
     * @var array<string, array<int, string|bool>>
     */
    private const BIREYSEL = [
        'ad_soyad' => ['Ad soyad', 'metin', 'basvuru:basvuran_ad'],
        'adres' => ['Adres', 'metin-uzun', 'form:adres'],
        'il_ilce' => ['İl / ilçe', 'il-ilce', 'form:il+ilce'],
        'telefon' => ['Telefon', 'telefon', 'basvuru:basvuran_telefon'],
        /*
         * 🔒 E-POSTA DÜZELTİLEMEZ. Düzeltme bileti tam da o adrese gidiyor;
         * yanlış olsaydı kişi bu sayfayı hiç göremezdi. Buradan
         * değiştirilebilseydi ele geçirilmiş bir bilet, açılacak HESABIN
         * adresini de değiştirebilirdi. Yetkili işaretleyebilir, başvuran
         * açıklama kutusundan yanıtlar.
         */
        'eposta' => ['E-posta', 'metin', 'basvuru:basvuran_eposta', false],
        'sosyal_medya' => ['Sosyal medya', 'sosyal', 'form:sosyal_medya'],
        // 🔒 Kurum bağı yalnızca AKREDİTE kurumlar arasından kurulabilir ve
        // kontenjan/teyit zincirini değiştirir: panelden yürür.
        'kurum' => ['Kurum', 'metin', 'basvuru:kurum_id', false],
        'basin_karti' => ['Basın kartı', 'evet-hayir', 'form:basin_karti_var'],
        'sigorta_212' => ['212 sigortası', 'evet-hayir', 'form:sigorta_212_var'],
        'calisma_yili' => ['Mesleki deneyim (yıl)', 'sayi', 'form:calisma_yili'],
    ];

    /** @var array<string, array<int, string|bool>> */
    private const KURUMSAL = [
        'resmi_unvan' => ['Resmi ünvan', 'metin', 'kurum:resmi_unvan'],
        'adres' => ['Adres', 'metin-uzun', 'kurum:adres'],
        'il_ilce' => ['İl / ilçe', 'il-ilce', 'kurum:il+ilce'],
        'telefon' => ['Telefon', 'telefon', 'kurum:telefon'],
        'eposta' => ['E-posta', 'metin', 'kurum:eposta', false],
        'vergi_dairesi' => ['Vergi dairesi', 'metin', 'kurum:vergi_dairesi'],
        'vergi_no' => ['Vergi numarası', 'vergi-no', 'kurum:vergi_no'],
        'calisan_araligi' => ['Çalışan sayısı', 'aralik', 'kurum:calisan_araligi'],
        'yayin_platformlari' => ['Yayın platformları', 'platformlar', 'kurum:yayin_platformlari'],
        'sosyal_medya' => ['Sosyal medya', 'sosyal', 'kurum:sosyal_medya'],
        'yetkili_ad' => ['Yetkili adı soyadı', 'metin', 'basvuru:basvuran_ad'],
        'yetkili_telefon' => ['Yetkili telefonu', 'telefon', 'basvuru:basvuran_telefon'],
    ];

    /**
     * Türe göre veri alanlarının tam tanımı.
     *
     * @return array<string, array{etiket: string, tip: string, kaynak: string, duzeltilebilir: bool}>
     */
    public static function veriTanimlari(BasvuruTuru $tur): array
    {
        $ham = $tur === BasvuruTuru::Kurum ? self::KURUMSAL : self::BIREYSEL;

        $tanimlar = [];

        foreach ($ham as $alan => $satir) {
            $tanimlar[self::VERI_ONEK.$alan] = [
                'etiket' => $satir[0],
                'tip' => $satir[1],
                'kaynak' => $satir[2],
                'duzeltilebilir' => $satir[3] ?? true,
            ];
        }

        return $tanimlar;
    }

    /**
     * Türe göre veri alanları: anahtar => etiket.
     *
     * @return array<string, string>
     */
    public static function veriAlanlari(BasvuruTuru $tur): array
    {
        return collect(self::veriTanimlari($tur))
            ->map(fn (array $tanim) => $tanim['etiket'])
            ->all();
    }

    /** @return array{etiket: string, tip: string, kaynak: string, duzeltilebilir: bool}|null */
    public static function tanim(BasvuruTuru $tur, string $anahtar): ?array
    {
        return self::veriTanimlari($tur)[$anahtar] ?? null;
    }

    public static function veriMi(string $anahtar): bool
    {
        return str_starts_with($anahtar, self::VERI_ONEK);
    }

    /** Ek talep anahtarı: alan listemizde olmayan, elle tanımlanan istek. */
    public static function ekMi(string $anahtar): bool
    {
        return str_starts_with($anahtar, self::EK_ONEK);
    }

    /**
     * Bu başvuruda işaretlenebilecek TÜM alanlar: anahtar => etiket.
     *
     * @return array<string, string>
     */
    public static function tumu(Basvuru $basvuru): array
    {
        $alanlar = self::veriAlanlari($basvuru->tur);

        foreach (EvrakTuru::turIcin($basvuru->tur) as $tur) {
            $alanlar[self::EVRAK_ONEK.$tur->kod] = $tur->ad;
        }

        return $alanlar;
    }

    /**
     * Anahtarın ekranda görünecek adı. Tanınmayan anahtar (eski bilet)
     * olduğu gibi döner -- yolda olan düzeltme boş etiketle gösterilmesin.
     *
     * 🪤 `ek:` anahtarlarının etiketi ŞEMADA YOK: yetkili onları elle
     * yazıyor ve etiket turun `ek_talepler` alanında duruyor. Buraya
     * bakmadan ekranda ham anahtar ("ek:1") görünüyordu.
     */
    public static function etiket(Basvuru $basvuru, string $anahtar): string
    {
        if (self::ekMi($anahtar)) {
            foreach ($basvuru->duzeltmeler as $tur) {
                foreach ($tur->ek_talepler ?? [] as $ek) {
                    if (($ek['anahtar'] ?? null) === $anahtar) {
                        return $ek['etiket'];
                    }
                }
            }

            return $anahtar;
        }

        return self::tumu($basvuru)[$anahtar] ?? $anahtar;
    }

    public static function evrakMi(string $anahtar): bool
    {
        return str_starts_with($anahtar, self::EVRAK_ONEK);
    }

    /**
     * Bir evrak türü bu bilette isteniyor mu? Yeni anahtar (`evrak:kod`) ve
     * geçiş süresince eski çıplak ad, ikisi de kabul edilir.
     *
     * @param  array<int, string>  $isaretli
     */
    public static function evrakIsteniyorMu(EvrakTuru $tur, array $isaretli): bool
    {
        return in_array(self::EVRAK_ONEK.$tur->kod, $isaretli, true)
            || in_array($tur->ad, $isaretli, true);
    }
}
