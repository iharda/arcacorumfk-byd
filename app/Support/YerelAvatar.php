<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Panel avatarlari YERELDE uretilir.
 *
 * 🔒 Filament'in varsayilani ui-avatars.com'a istek atar ve kullanicinin ADINI
 * (Gravatar kullanilirsa e-posta hash'ini) UCUNCU TARAFA gonderir. Basin
 * mensuplarinin kimlik verisini tutan bir sistemde bu KVKK acisindan kabul
 * edilemez; ayrica CSP'de img-src 'self' oldugu icin gorsel zaten kirik cikardi.
 *
 * Cozum: bas harflerden data: URI olarak SVG. Dis istek YOK.
 */
class YerelAvatar implements AvatarProvider
{
    /** Kulüp paletinden türetilmiş, okunabilir zemin renkleri. */
    private const ZEMINLER = ['#C11119', '#920011', '#7A1420', '#2F3A45', '#4A4F55', '#1F2937'];

    public function get(Model $record): string
    {
        $ad = trim((string) ($record->getAttribute('name') ?? ''));
        $harfler = $this->basHarfler($ad);

        // Ayni kisi her zaman ayni rengi alsin (listede goz alisir).
        $zemin = self::ZEMINLER[crc32($ad ?: 'byd') % count(self::ZEMINLER)];

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="96" height="96">
          <rect width="96" height="96" fill="{$zemin}"/>
          <text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff"
                font-family="system-ui, -apple-system, Segoe UI, Roboto, sans-serif"
                font-size="38" font-weight="600">{$harfler}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function basHarfler(string $ad): string
    {
        $parcalar = preg_split('/\s+/u', $ad, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parcalar === []) {
            return 'BYD';
        }

        $ilk = mb_substr($parcalar[0], 0, 1);
        $son = count($parcalar) > 1 ? mb_substr(end($parcalar), 0, 1) : '';

        // ⚠️ Türkçe: buyuk harfe cevirmeden ONCE i → İ. Sonra yapilirsa
        // mb_strtoupper zaten "I" uretmis olur ve degistirecek "i" kalmaz.
        return mb_strtoupper(str_replace('i', 'İ', $ilk.$son), 'UTF-8');
    }
}
