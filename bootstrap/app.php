<?php

use App\Support\YanlisPanelYonlendirmesi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * 🪤 `auth` ara katmanı, oturumsuz isteği varsayılan olarak route('login')
         * adresine yollar. Bu uygulamada "login" adlı rota YOK → 500.
         *
         * Evrak ucu bir API gibi davranır: yönlendirme yerine 401. Yönetim
         * paneli kendi 2FA'lı kapısına, kalan herkes TEK giriş sayfasına düşer
         * (Revizyon md.4.3).
         */
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('evrak/*') => null,
            $request->is('yonetim', 'yonetim/*') => route('filament.yonetim.auth.login'),
            default => route('giris'),
        });

        // Cloudflare önde: gerçek ziyaretçi IP'si nginx'in yazdığı başlıktan gelir.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Yanlış panele düşen oturumlu kullanıcı, menüsüz ve çıkışsız bir
         * "403 Yasak" sayfasında kalmasın; kendi paneline gitsin.
         * Ayrıntı ve sınırları YanlisPanelYonlendirmesi'nde yazılı.
         */
        $exceptions->render(
            fn (HttpExceptionInterface $e, Request $request) => YanlisPanelYonlendirmesi::yanit($e, $request),
        );
    })->create();
