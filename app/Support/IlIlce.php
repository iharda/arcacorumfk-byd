<?php

namespace App\Support;

/**
 * İl ve ilçe listesi -- Revizyon md.5.1.
 *
 * 81 il ve 973 ilçe DEĞİŞMEYEN veridir; tablo açmak yerine tek bir JSON
 * dosyasında durur (`resources/data/il-ilce.json`). Kaynak: `turkey-neighbourhoods`
 * paketinin resmi listesi; her ilin ilçeleri Türkçe alfabetik, "Merkez" başta.
 *
 * 🔑 Sunucu doğrulaması BU listeden geçer: istemcinin gönderdiği il/ilçe
 * çiftine güvenilmez, listede yoksa reddedilir.
 */
class IlIlce
{
    /** @var ?array<string, array<int, string>> */
    private static ?array $veri = null;

    /** @return array<string, array<int, string>> */
    public static function tumu(): array
    {
        if (self::$veri !== null) {
            return self::$veri;
        }

        $ham = file_get_contents(resource_path('data/il-ilce.json'));
        $cozulen = $ham === false ? null : json_decode($ham, true);

        return self::$veri = is_array($cozulen) ? $cozulen : [];
    }

    /** @return array<int, string> */
    public static function iller(): array
    {
        return array_keys(self::tumu());
    }

    /** @return array<int, string> */
    public static function ilceler(string $il): array
    {
        return self::tumu()[$il] ?? [];
    }

    public static function gecerliMi(?string $il, ?string $ilce): bool
    {
        return $il !== null && $ilce !== null && in_array($ilce, self::ilceler($il), true);
    }
}
