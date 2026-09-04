<?php

namespace App\Support;

use App\Models\Ayar;
use Illuminate\Support\Carbon;

/**
 * Akreditasyon sezonu -- Tutarsızlık incelemesi M9 №2.
 *
 * 💀 KORUNAN ASIL DAVRANIŞ: `Akreditasyon::gecerliMi()` boş `gecerlilik_bitis`
 * değerini "süresiz geçerli" sayar. Sütunu hiçbir ekran doldurmadığı için
 * sistemdeki BÜTÜN kartlar süresizdi: sezon bitse de geçen sezonun kartı
 * turnikeden geçmeye devam ederdi. `DikkatGerektirenler::suresiBitecekler()`
 * de bu sütuna baktığı için pano kutusu hiç çizilmiyordu.
 *
 * Sezon bilgisi TEK yerden okunur; üç yerde kullanılıyor:
 *   1. yeni akreditasyon doğarken (AkreditasyonAkisi),
 *   2. "Sezonu uygula" toplu eylemi (AkreditasyonlarTable),
 *   3. pano uyarısı (DikkatGerektirenler).
 *
 * ⚠️ Sezon Temmuz'da döner, takvim yılı Ocak'ta: `kart_yil` ayarıyla aynı şey
 * DEĞİLDİR. O numaradaki yılı belirler, bu kartın ne zaman geçersizleşeceğini.
 */
class Sezon
{
    /** "2026 / 2027" gibi; boşsa sezon tanımlanmamıştır. */
    public static function ad(): ?string
    {
        $ad = Ayar::al('sezon');

        return filled($ad) ? (string) $ad : null;
    }

    public static function baslangic(): ?Carbon
    {
        return self::tarih('sezon_baslangic');
    }

    public static function bitis(): ?Carbon
    {
        return self::tarih('sezon_bitis');
    }

    /**
     * Sezon kullanılabilir durumda mı? Bitiş tarihi ŞART: asıl amaç kartın
     * bir gün sona ermesi. Yalnızca ad girilmiş bir sezon bu işi görmez.
     */
    public static function tanimliMi(): bool
    {
        return self::bitis() !== null;
    }

    /**
     * Yeni akreditasyona yazılacak alanlar.
     *
     * Sezon tanımlı değilse BOŞ dizi döner ve alanlar eskisi gibi boş kalır --
     * yarım yapılandırmayla kart üretimini durdurmak, kulübü maç günü
     * kilitlemek olurdu. Eksiklik pano uyarısıyla söylenir.
     *
     * @return array<string, mixed>
     */
    public static function alanlar(): array
    {
        if (! self::tanimliMi()) {
            return [];
        }

        return array_filter([
            'sezon' => self::ad(),
            'gecerlilik_baslangic' => self::baslangic(),
            'gecerlilik_bitis' => self::bitis(),
        ]);
    }

    private static function tarih(string $anahtar): ?Carbon
    {
        $deger = Ayar::al($anahtar);

        return filled($deger) ? Carbon::parse((string) $deger)->startOfDay() : null;
    }
}
