<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use App\Support\KulupRengi;
use App\Support\YerelAvatar;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * YETKILI PANELI -- kulup. Plan v1.0 md.8.
 * Basvuru kuyrugu, yan yana evrak incelemesi, karar, akreditasyon yonetimi.
 *
 * 🔒 Iki adimli dogrulama ZORUNLU (md.11). Kullanici ilk giriste kurmaya
 * yonlendirilir; kurmadan panele giremez.
 * ⏳ md.11 ayrica "admin panel ayri subdomain, gerekirse IP kisitli" diyor --
 * canliya cikmadan once yonetim.<alanadi> olarak ayrilacak.
 */
class YonetimPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('yonetim')
            ->path('yonetim')
            ->brandName('ARCA Çorum FK · Basın Yönetim Sistemi')
            // Kulüp arması + sistem adı birlikte (arma tek başına 48px'te okunmuyor).
            ->brandLogo(fn () => view('filament.marka', ['altBaslik' => 'Basın Yönetim Sistemi']))
            // 🪤 Htmlable marka SABİT yükseklikli bir div'e sarılır (varsayılan
            //    1.5rem). Bunu yazmazsan içerik taşar ve 'Oturum Aç' başlığının
            //    üstüne biner.
            ->brandLogoHeight('3rem')
            ->favicon(asset('marka/favicon-64.png'))
            ->colors([
                'primary' => KulupRengi::birincil(),   // kulüp kırmızısı #C11119
            ])
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            // Avatar YERELDE uretilir; ui-avatars.com'a kullanıcı adı GİTMEZ.
            ->defaultAvatarProvider(YerelAvatar::class)
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Yonetim/Resources'), for: 'App\Filament\Yonetim\Resources')
            ->discoverPages(in: app_path('Filament/Yonetim/Pages'), for: 'App\Filament\Yonetim\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Yonetim/Widgets'), for: 'App\Filament\Yonetim\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
