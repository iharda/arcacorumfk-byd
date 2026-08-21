<?php

namespace App\Filament\Ortak;

use App\Servisler\IcerikAkisi;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Medya merkezi içerik sayfalarının ortak tabanı -- Plan v1.0 md.8.
 *
 * 🔒 İçerik YALNIZCA akredite kullanıcıya açıktır. Kontrol hem menüde
 * (görünmesin) hem de mount'ta (adresi bilen giremesin) yapılır — menüyü
 * gizlemek tek başına yetki değildir.
 */
abstract class MedyaMerkeziSayfasi extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::akrediteMi();
    }

    public function mount(): void
    {
        abort_unless(static::akrediteMi(), 403);
    }

    protected static function akrediteMi(): bool
    {
        return IcerikAkisi::akrediteKullanicilar()->whereKey(Auth::id())->exists();
    }
}
