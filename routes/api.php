<?php

use App\Http\Controllers\KapiController;
use App\Http\Middleware\KapiIstemcisiDogrula;
use Illuminate\Support\Facades\Route;

/*
 * Turnike / gişe doğrulama API'si -- Plan v1.0 md.7.
 * Oturum YOK: her istek kendi anahtarıyla kimliklenir (X-Kapi-Anahtar).
 * IP kısıtı ve hız sınırı ara katmanda.
 */
Route::middleware([KapiIstemcisiDogrula::class, 'throttle:kapi'])
    ->prefix('kapi')
    ->group(function () {
        Route::get('/tanim', [KapiController::class, 'tanim'])->name('api.kapi.tanim');
        Route::post('/dogrula', [KapiController::class, 'dogrula'])->name('api.kapi.dogrula');
    });
