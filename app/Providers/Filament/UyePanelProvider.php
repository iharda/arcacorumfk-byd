<?php

namespace App\Providers\Filament;

use App\Filament\Uye\Pages\Pano;
use App\Support\KulupRengi;
use App\Support\YerelAvatar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
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
            // Kulüp arması + sistem adı birlikte (arma tek başına 48px'te okunmuyor).
            ->brandLogo(fn () => view('filament.marka', ['altBaslik' => 'Basın Paneli']))
            // 🪤 Htmlable marka SABİT yükseklikli bir div'e sarılır (varsayılan
            //    1.5rem). Bunu yazmazsan içerik taşar ve 'Oturum Aç' başlığının
            //    üstüne biner.
            ->brandLogoHeight('3rem')
            ->favicon(asset('marka/favicon-64.png'))
            ->colors([
                'primary' => KulupRengi::birincil(),   // kulüp kırmızısı #C11119
            ])
            /*
             * 🔑 Bu panelin KENDİ giriş sayfası YOK (Revizyon md.4.3): kurum,
             * basın mensubu ve içerik üreticisi tek kapıdan girer (`/giris`).
             * Şifre sıfırlama da tek rotada: `/sifremi-unuttum`.
             * Oturumsuz istek `redirectGuestsTo` ile oraya düşer.
             */
            ->emailVerification()
            ->profile(isSimple: false)
            // Avatar YERELDE uretilir; ui-avatars.com'a kullanıcı adı GİTMEZ.
            ->defaultAvatarProvider(YerelAvatar::class)
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Uye/Resources'), for: 'App\Filament\Uye\Resources')
            ->discoverPages(in: app_path('Filament/Uye/Pages'), for: 'App\Filament\Uye\Pages')
            // 🔤 Filament'in çıplak `Dashboard`'ı DEĞİL: menüde "Dashboard" yazıyordu.
            ->pages([Pano::class])
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
