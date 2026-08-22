<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Girişten sonra kullanıcıyı KENDİ paneline götürür.
 *
 * 💥 Filament'in varsayılanı `redirect()->intended(...)` çağırır. `url.intended`
 * oturumda TUTULUR ve giriş sırasında temizlenmez; üç panel de aynı `web`
 * oturumunu paylaştığı için başka bir panelden düşmüş adres orada bekler.
 * Gerçek olay: tarayıcıda önce /yonetim/kapilar açılmış (oturum kapanınca
 * url.intended oraya set olmuş), sonra /kurum/login'den kurum kullanıcısıyla
 * girilmiş → Filament onu /yonetim/kapilar'a yollamış → canAccessPanel('yonetim')
 * false → çıkışı olmayan **403 Yasak** sayfası.
 *
 * Kural: hedef, girilen panelin ALTINDA değilse atılır.
 */
class PanelGirisYaniti implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $panelKok = Filament::getUrl();

        $hedef = $request->session()->get('url.intended');

        if (is_string($hedef) && ! $this->panelIcinde($hedef, $panelKok)) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($panelKok);
    }

    /** Hedef adres, panelin kök yolunun altında mı? */
    private function panelIcinde(string $hedef, string $panelKok): bool
    {
        $h = $this->yol($hedef);
        $k = $this->yol($panelKok);

        // 🪤 Düz `str_starts_with` yetmez: /kurum kökü /kurumsal-x'i de kabul eder.
        return $h === $k || str_starts_with($h, rtrim($k, '/').'/');
    }

    private function yol(string $url): string
    {
        return '/'.trim((string) (parse_url($url, PHP_URL_PATH) ?? '/'), '/');
    }
}
