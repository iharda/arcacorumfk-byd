<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Panodaki cumle icindeki tarih ve sayilarin TURKCE yazimi --
 * Cuneyt Bey revizyonu (03.09.2026): "20 Ağustos 2026'da alındı",
 * ve "Türkçe sayı gösteriminde '2.7 gün' yerine '2,7 gün' kullanılmalı."
 */
class TarihMetni
{
    /**
     * Yila gelen bulunma hali eki. Ek, yilin OKUNUSUNUN son unlusune ve
     * son sessizine bakar: 2026 "iki bin yirmi altı" → `'da`,
     * 2027 "… yedi" → `'de`, 2024 "… dört" → `'te`.
     *
     * 🪤 Rakama bakip tek bir ek secmek YANLIS: ayni onlukta hem 'da hem
     * 'te cikabiliyor (2024'te / 2026'da).
     */
    private const BIRLER = [1 => "'de", 2 => "'de", 3 => "'te", 4 => "'te", 5 => "'te",
        6 => "'da", 7 => "'de", 8 => "'de", 9 => "'da"];

    private const ONLAR = [1 => "'da", 2 => "'de", 3 => "'da", 4 => "'ta", 5 => "'de",
        6 => "'ta", 7 => "'te", 8 => "'de", 9 => "'da"];

    public static function yilEki(int $yil): string
    {
        $birler = $yil % 10;

        if ($birler !== 0) {
            return self::BIRLER[$birler];
        }

        $onlar = intdiv($yil % 100, 10);

        if ($onlar !== 0) {
            return self::ONLAR[$onlar];
        }

        // "… yüz" ve "… bin" ile biten yillar: ikisi de ince, `'de`.
        return "'de";
    }

    /** "20 Ağustos 2026" -- ay adi uygulama diline gore. */
    public static function uzun(Carbon $an): string
    {
        return $an->timezone('Europe/Istanbul')->translatedFormat('j F Y');
    }

    /** "20 Ağustos 2026'da" -- cumle icinde kullanilir. */
    public static function uzunEkli(Carbon $an): string
    {
        $an = $an->timezone('Europe/Istanbul');

        return self::uzun($an).self::yilEki((int) $an->format('Y'));
    }

    /** Ondalik ayraci VIRGUL, binlik ayraci NOKTA -- Türkçe yazim. */
    public static function sayi(float $deger, int $basamak = 1): string
    {
        return number_format($deger, $basamak, ',', '.');
    }
}
