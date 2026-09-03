<?php

namespace App\Filament\Ortak;

use App\Servisler\IcerikAkisi;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
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

    /**
     * "Yeni" rozetinin esigi: bu listeye BIR ONCEKI bakis ani.
     * Damgasi olmayan sayfalarda null kalir, hicbir sey yeni sayilmaz.
     */
    public ?Carbon $esik = null;

    public function mount(): void
    {
        abort_unless(static::akrediteMi(), 403);

        $this->gorulduIsaretle();
    }

    /**
     * Sayfanin kendi gorulme damgasi -- yoksa null.
     * Duyurular ve bultenler AYRI damga tutar: birini acmak digerinin
     * rozetini dusurmemeli.
     */
    protected static function gorulmeAlani(): ?string
    {
        return null;
    }

    /**
     * 🪤 Esik ONCE okunur, damga SONRA guncellenir -- yoksa kullanici sayfayi
     * acar acmaz her sey "okundu" olur ve rozet hic gorunmez.
     *
     * 🪤 `forceFill`: alan Fillable listesinde DEGIL (olmamali da). `update()`
     * ile yazmaya calisirsak sessizce duserdi.
     */
    private function gorulduIsaretle(): void
    {
        $alan = static::gorulmeAlani();

        if ($alan === null) {
            return;
        }

        $kullanici = Auth::user();
        $this->esik = $kullanici->{$alan};

        $kullanici->forceFill([$alan => now()])->save();
    }

    protected static function akrediteMi(): bool
    {
        return IcerikAkisi::akrediteKullanicilar()->whereKey(Auth::id())->exists();
    }
}
