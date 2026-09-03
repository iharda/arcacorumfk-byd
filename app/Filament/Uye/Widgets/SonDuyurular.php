<?php

namespace App\Filament\Uye\Widgets;

use App\Models\Bulten;
use App\Models\Duyuru;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Son duyurular + son bülten satırı -- briefi md. B.1, Widget 4.
 *
 * Bülten için ayrı kutu açılmadı: haftada bir çıkan tek kayıt için ekranda
 * ayrı bir kutu boş durur.
 */
class SonDuyurular extends Widget
{
    protected string $view = 'filament.uye.widgets.son-duyurular';

    protected static ?int $sort = 4;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::check();
    }

    /** @return Collection<int, Duyuru> */
    public static function kayitlar(): Collection
    {
        return self::$onbellek ??= Duyuru::query()
            ->yayinda()
            ->orderByDesc('yayin_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    /** @return Collection<int, Duyuru> */
    public function getKayitlarProperty(): Collection
    {
        return static::kayitlar();
    }

    public function getSonBultenProperty(): ?Bulten
    {
        return Bulten::query()->yayinda()->orderByDesc('yayin_at')->orderByDesc('id')->first();
    }
}
