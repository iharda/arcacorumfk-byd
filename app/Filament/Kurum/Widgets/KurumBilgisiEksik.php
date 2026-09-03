<?php

namespace App\Filament\Kurum\Widgets;

use App\Models\Kurum;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Eksik kurum bilgisi uyarısı -- briefi md. B.2, Widget 4.
 *
 * Eksik kurum bilgisi akreditasyon YENİLEMESİNDE sorun çıkarıyor; sorun
 * çıkmadan önce söylenmeli. Eksik yoksa kutu hiç görünmez.
 */
class KurumBilgisiEksik extends Widget
{
    protected string $view = 'filament.kurum.widgets.kurum-bilgisi-eksik';

    protected static ?int $sort = 3;

    /** alan => ekranda görünen ad */
    private const ALANLAR = [
        'vergi_no' => 'Vergi numarası',
        'adres' => 'Adres',
        'telefon' => 'Telefon',
        'eposta' => 'E-posta',
        'yayin_platformlari' => 'Yayın platformları',
    ];

    private static ?array $onbellek = null;

    public static function canView(): bool
    {
        return Auth::user()?->kurum !== null && static::eksikler() !== [];
    }

    /** @return array<int, string> */
    public static function eksikler(): array
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kurum = Auth::user()?->kurum;

        if (! $kurum instanceof Kurum) {
            return self::$onbellek = [];
        }

        $eksik = [];

        foreach (self::ALANLAR as $alan => $etiket) {
            // 🪤 `blank()` boş diziyi de yakalar: `yayin_platformlari` jsonb,
            // hiç girilmemişse `[]` gelir ve `empty string` değildir.
            if (blank($kurum->getAttribute($alan))) {
                $eksik[] = $etiket;
            }
        }

        return self::$onbellek = $eksik;
    }

    /** @return array<int, string> */
    public function getEksiklerProperty(): array
    {
        return static::eksikler();
    }
}
