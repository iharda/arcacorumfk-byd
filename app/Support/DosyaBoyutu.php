<?php

namespace App\Support;

/**
 * Bayt sayısını insanın okuyabileceği hâle getirir.
 *
 * 🇹🇷 Ondalık ayırıcı VİRGÜL: "1,4 MB". Revizyon 4.6'da "2.7 gün" düzeltilmişti,
 * aynı kural dosya boyutunda da geçerli.
 */
class DosyaBoyutu
{
    private const BIRIMLER = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function metin(?int $bayt): string
    {
        if ($bayt === null || $bayt < 0) {
            return '—';
        }

        $birim = 0;
        $deger = (float) $bayt;

        while ($deger >= 1024 && $birim < count(self::BIRIMLER) - 1) {
            $deger /= 1024;
            $birim++;
        }

        // Bayt tam sayı kalır (yarım bayt yok); üstü tek ondalıkla yeter.
        $basamak = $birim === 0 ? 0 : 1;

        return number_format($deger, $basamak, ',', '.').' '.self::BIRIMLER[$birim];
    }
}
