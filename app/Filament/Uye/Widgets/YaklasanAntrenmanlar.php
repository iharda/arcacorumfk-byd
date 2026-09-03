<?php

namespace App\Filament\Uye\Widgets;

use App\Models\Antrenman;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Yaklaşan üç antrenman -- briefi md. B.1, Widget 3.
 *
 * 🔒 Yalnızca YAYINDA ve BASINA AÇIK olanlar. Basına kapalı antrenman üye
 * panelinde hiç görünmemeli; koşul sorguda, ekranda değil.
 */
class YaklasanAntrenmanlar extends Widget
{
    protected string $view = 'filament.uye.widgets.yaklasan-antrenmanlar';

    protected static ?int $sort = 3;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::check();
    }

    /** @return Collection<int, Antrenman> */
    public static function kayitlar(): Collection
    {
        return self::$onbellek ??= Antrenman::query()
            ->yayinda()
            ->where('basina_acik', true)
            ->yaklasan()
            ->limit(3)
            ->get();
    }

    /** @return Collection<int, Antrenman> */
    public function getKayitlarProperty(): Collection
    {
        return static::kayitlar();
    }
}
