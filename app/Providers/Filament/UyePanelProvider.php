<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use App\Support\YerelAvatar;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * UYE PANELI -- basin mensubu / icerik ureticisi. Plan v1.0 md.8.
 * Kartim (goruntule + PDF), duyurular, antrenman takvimi, bultenler, profil.
 *
 * ⚠️ Onay ONCESI de acilir: o asamada yalnizca basvuru durumu + evrak yukleme
 * gorunur (md.5.5).
 */
class UyePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('uye')
            ->path('panel')
            ->brandName('Basın Paneli')
            ->colors([
                'primary' => Color::hex('#C11119'),
            ])
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->profile(isSimple: false)
            // Avatar YERELDE uretilir; ui-avatars.com'a kullanıcı adı GİTMEZ.
            ->defaultAvatarProvider(YerelAvatar::class)
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Uye/Resources'), for: 'App\Filament\Uye\Resources')
            ->discoverPages(in: app_path('Filament/Uye/Pages'), for: 'App\Filament\Uye\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Uye/Widgets'), for: 'App\Filament\Uye\Widgets')
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
