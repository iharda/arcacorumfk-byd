<?php

namespace App\Support;

/**
 * ARCA Çorum FK kulüp kırmızısı — panel paleti.
 *
 * 🪤 `Color::hex('#C11119')` İŞE YARAMAZ: Filament 5 verilen renkten YALNIZCA
 * TON AÇISINI (hue) alır, açıklık ve doygunluğu kendi sabit merdiveninden
 * uygular. Sonuç: düğmeler oklch(0.598 0.169) ile SOMON çıkar, kulüp kırmızısı
 * değil. Bu yüzden merdiveni elle yazıyoruz.
 *
 * Çapa noktaları:  600 = #C11119 (düğme/vurgu)  ·  700 = #920011 (koyu ton)
 */
class KulupRengi
{
    private const TON = 27.1;   // #C11119'un oklch ton açısı

    /** @var array<int, array{float, float}> shade => [açıklık, doygunluk] */
    private const MERDIVEN = [
        50  => [0.977, 0.014],
        100 => [0.950, 0.035],
        200 => [0.905, 0.070],
        300 => [0.840, 0.118],
        400 => [0.740, 0.170],
        500 => [0.620, 0.196],
        600 => [0.5152, 0.2028],   // #C11119
        700 => [0.4155, 0.1688],   // #920011
        800 => [0.360, 0.140],
        900 => [0.315, 0.112],
        950 => [0.230, 0.078],
    ];

    /** @return array<int, string> */
    public static function birincil(): array
    {
        return array_map(
            fn (array $d): string => "oklch({$d[0]} {$d[1]} " . self::TON . ')',
            self::MERDIVEN,
        );
    }
}
