<?php

namespace App\Filament\Kurum\Widgets;

use App\Support\SonIcerikListesi;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Duyuru / bülten / antrenman karışık son kayıtlar -- briefi md. B.2, Widget 5.
 * Normalize işi `App\Support\SonIcerikListesi`'nde; burada yalnızca gösterim.
 */
class SonIcerikler extends Widget
{
    protected string $view = 'filament.kurum.widgets.son-icerikler';

    protected static ?int $sort = 4;

    private const ADET = 4;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::check() && static::satirlar()->isNotEmpty();
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function satirlar(): Collection
    {
        return self::$onbellek ??= SonIcerikListesi::son(self::ADET, [
            'duyuru' => route('filament.kurum.pages.duyurular'),
            'bulten' => route('filament.kurum.pages.bultenler'),
            'antrenman' => route('filament.kurum.pages.takvim'),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getSatirlarProperty(): Collection
    {
        return static::satirlar();
    }
}
