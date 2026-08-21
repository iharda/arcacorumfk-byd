<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
         * adresine yollar. Bu uygulamada "login" adlı rota YOK (her panelin kendi
         * girişi var) → RouteNotFoundException → kullanıcı 403 yerine 500 görür.
         *
         * Evrak ucu bir API gibi davranır: yönlendirme yerine 401. Diğer yerlerde
         * ana sayfaya düşürülür; hangi panele ait olduğu SIZDIRILMAZ.
         */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('evrak/*') ? null : route('anasayfa'),
        );

        // Cloudflare önde: gerçek ziyaretçi IP'si nginx'in yazdığı başlıktan gelir.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
