<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Girişten sonra gidilecek adres.
 *
 * 💥 `url.intended` oturumda TUTULUR ve giriş sırasında temizlenmez; üç panel
 * aynı `web` oturumunu paylaştığı için başka bir panelden düşmüş adres orada
 * bekler. Gerçek olay: önce /yonetim/kapilar açılmış, sonra kurum kullanıcısı
 * girmiş → Filament onu /yonetim/kapilar'a yollamış → çıkışsız 403.
 *
 * Kural: bekleyen hedef, gidilecek panelin ALTINDA değilse atılır.
 */
class GirisHedefi
{
    public static function belirle(Request $istek, string $panelKok): string
    {
        $hedef = $istek->session()->pull('url.intended');

        return is_string($hedef) && self::panelIcinde($hedef, $panelKok)
            ? $hedef
            : $panelKok;
    }

    private static function panelIcinde(string $hedef, string $panelKok): bool
    {
        $h = self::yol($hedef);
        $k = self::yol($panelKok);

        // 🪤 Düz `str_starts_with` yetmez: /kurum kökü /kurumsal-x'i de kabul eder.
        return $h === $k || str_starts_with($h, rtrim($k, '/').'/');
    }

    private static function yol(string $url): string
    {
        return '/'.trim((string) (parse_url($url, PHP_URL_PATH) ?? '/'), '/');
    }
}
