<?php

namespace App\Providers\Filament;

use App\Filament\Yonetim\Pages\Pano;
use App\Filament\Yonetim\Widgets\OzetSayilar;
use App\Support\KulupRengi;
use App\Support\YerelAvatar;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
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
            // Yetkilinin kapısı AYRI kalır: 2FA burada zorunlu, tek giriş
            // sayfası (`/giris`) yetkiliyi içeri almaz (Revizyon md.4.2).
            ->login()
            /*
             * 🪤 `->passwordReset()` KALDIRILDI: şifre sıfırlama tek rotada
             * (`/sifremi-unuttum`). Üç ayrı sıfırlama kapısı, üç ayrı e-posta
             * biçimi demekti. Giriş sayfasındaki bağlantı aşağıdaki render
             * kancasıyla eklenir, yoksa yetkili çıkmaza düşerdi.
             */
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('filament.yonetim.sifre-baglantisi'),
            )
            ->profile(isSimple: false)
            /*
             * ⚠️ İkinci argümandaki varsayılan (true) BİLEREK: `config:cache`
             * uygulamayı ESKİ önbellekle açar; yeni bir ayar anahtarı henüz
             * orada yoksa null gelir, panel çöker ve çöktüğü için önbellek de
             * yenilenemez — kilitlenirsin. Varsayılan bu döngüyü kırar ve
             * güvenli tarafa (2FA açık) düşer.
             */
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: (bool) config('bys.2fa_zorunlu', true))
            // Avatar YERELDE uretilir; ui-avatars.com'a kullanıcı adı GİTMEZ.
            ->defaultAvatarProvider(YerelAvatar::class)
            ->databaseNotifications()
            // ⚠️ sidebarCollapsibleOnDesktop() KALDIRILDI: menü ikon-only açılıyor,
            //    etiketler görünmüyordu. Yetkili sisteme haftada birkaç kez
            //    giriyor; ikonları ezberlemesini bekleyemeyiz.
            ->discoverResources(in: app_path('Filament/Yonetim/Resources'), for: 'App\Filament\Yonetim\Resources')
            ->discoverPages(in: app_path('Filament/Yonetim/Pages'), for: 'App\Filament\Yonetim\Pages')
            // 🔤 Filament'in çıplak `Dashboard`'ı DEĞİL: menüde "Dashboard" yazıyordu.
            ->pages([Pano::class])
            ->discoverWidgets(in: app_path('Filament/Yonetim/Widgets'), for: 'App\Filament\Yonetim\Widgets')
            ->widgets([OzetSayilar::class])
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
