<?php

namespace App\Support;

/**
 * Telefon ülke kodları -- Revizyon md.5.2.
 *
 * Tam ITU listesi değil: Türkiye + kulübün gerçekten karşılaştığı yabancı basın
 * ülkeleri. Liste kısa kaldığı sürece açılır kutu aranabilir olmak zorunda
 * değil; uzarsa arama kutusu gerekir.
 */
class UlkeKodu
{
    public const VARSAYILAN = '+90';

    /** @return array<string, string> kod => görünen etiket */
    public static function hepsi(): array
    {
        return [
            '+90' => '🇹🇷 +90',
            '+49' => '🇩🇪 +49',
            '+44' => '🇬🇧 +44',
            '+31' => '🇳🇱 +31',
            '+32' => '🇧🇪 +32',
            '+33' => '🇫🇷 +33',
            '+43' => '🇦🇹 +43',
            '+41' => '🇨🇭 +41',
            '+39' => '🇮🇹 +39',
            '+34' => '🇪🇸 +34',
            '+30' => '🇬🇷 +30',
            '+359' => '🇧🇬 +359',
            '+380' => '🇺🇦 +380',
            '+7' => '🇷🇺 +7',
            '+994' => '🇦🇿 +994',
            '+995' => '🇬🇪 +995',
            '+971' => '🇦🇪 +971',
            '+974' => '🇶🇦 +974',
            '+1' => '🇺🇸 +1',
        ];
    }

    /** @return array<int, string> */
    public static function kodlar(): array
    {
        return array_keys(self::hepsi());
    }

    public static function gecerliMi(?string $kod): bool
    {
        return $kod !== null && in_array($kod, self::kodlar(), true);
    }
}
