<?php

namespace App\Http\Responses;

use App\Support\GirisHedefi;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Girişten sonra kullanıcıyı KENDİ paneline götürür.
 *
 * Yalnızca Filament'in kendi giriş sayfası (yönetim paneli) için geçerli;
 * kurum ve üye tek kapıdan (`/giris`) girer. Bekleyen hedefin elenmesi iki
 * yolda da AYNI kuralla yapılır: bkz. GirisHedefi.
 */
class PanelGirisYaniti implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->to(GirisHedefi::belirle($request, Filament::getUrl()));
    }
}
