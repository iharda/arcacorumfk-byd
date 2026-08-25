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

    /**
     * Türe göre veri alanları: anahtar => etiket.
     *
     * @return array<string, string>
     */
    public static function veriAlanlari(BasvuruTuru $tur): array
    {
        $ortak = [
            'ad_soyad' => 'Ad soyad',
            'adres' => 'Adres',
            'il_ilce' => 'İl / ilçe',
            'telefon' => 'Telefon',
            'eposta' => 'E-posta',
            'sosyal_medya' => 'Sosyal medya',
        ];

        $alanlar = $tur === BasvuruTuru::Kurum
            ? [
                'resmi_unvan' => 'Resmi ünvan',
                'adres' => 'Adres',
                'il_ilce' => 'İl / ilçe',
                'telefon' => 'Telefon',
                'eposta' => 'E-posta',
                'vergi_dairesi' => 'Vergi dairesi',
                'vergi_no' => 'Vergi numarası',
                'calisan_araligi' => 'Çalışan sayısı',
                'yayin_platformlari' => 'Yayın platformları',
                'sosyal_medya' => 'Sosyal medya',
                'yetkili_bilgileri' => 'Yetkili bilgileri',
            ]
            : $ortak + [
                'kurum' => 'Kurum',
                'basin_karti' => 'Basın kartı',
                'sigorta_212' => '212 sigortası',
                'calisma_yili' => 'Mesleki deneyim',
            ];

        return collect($alanlar)
            ->mapWithKeys(fn (string $etiket, string $alan) => [self::VERI_ONEK.$alan => $etiket])
            ->all();
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
     */
    public static function etiket(Basvuru $basvuru, string $anahtar): string
    {
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
