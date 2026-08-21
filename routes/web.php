<?php

use App\Http\Controllers\BasvuruController;
use App\Http\Controllers\EvrakController;
use App\Http\Controllers\KapiController;
use App\Http\Controllers\HesapController;
use Illuminate\Support\Facades\Route;

/*
 * Kamuya açık yüz. Panellerin rotalarını Filament kendi kaydeder
 * (/yonetim, /kurum, /panel).
 */

Route::get('/', [BasvuruController::class, 'secim'])->name('anasayfa');

Route::middleware('throttle:basvuru-goruntule')->group(function () {
    Route::get('/basvuru/kurum', [BasvuruController::class, 'kurumFormu'])->name('basvuru.kurum');
    // Hız sınırı ŞART: form hesap açıyor ve e-posta gönderiyor.
    Route::post('/basvuru/kurum', [BasvuruController::class, 'kurumKaydet'])
        ->middleware('throttle:basvuru-gonder')->name('basvuru.kurum.kaydet');
});

Route::middleware('throttle:basvuru-goruntule')->group(function () {
    Route::get('/basvuru/basin-mensubu', [BasvuruController::class, 'bireyselFormu'])
        ->name('basvuru.basin-mensubu');
    Route::get('/basvuru/icerik-ureticisi', [BasvuruController::class, 'bireyselFormu'])
        ->name('basvuru.icerik-ureticisi');
});
Route::middleware('throttle:basvuru-gonder')->group(function () {
    Route::post('/basvuru/basin-mensubu', [BasvuruController::class, 'bireyselKaydet'])
        ->name('basvuru.basin-mensubu.kaydet');
    Route::post('/basvuru/icerik-ureticisi', [BasvuruController::class, 'bireyselKaydet'])
        ->name('basvuru.icerik-ureticisi.kaydet');
});

/*
 * Davetle başvuru ("Yol B"). Token ham hâliyle YALNIZCA adreste; sunucuda
 * hash'i aranır. Kaba kuvvet için ayrı ve dar hız sınırı.
 */
Route::middleware('throttle:davet')->group(function () {
    Route::get('/davet/{token}', [BasvuruController::class, 'davetFormu'])->name('davet.form');
    Route::post('/davet/{token}', [BasvuruController::class, 'davetKaydet'])->name('davet.kaydet');
});

Route::get('/basvuru/gonderildi', [BasvuruController::class, 'gonderildi'])->name('basvuru.gonderildi');

// İmzalı + süreli bağlantı; ulid ile bağlanır, sıralı id adreste görünmez.
Route::middleware(['signed', 'throttle:hesap-aktivasyon'])->group(function () {
    Route::get('/hesap/aktivasyon/{kullanici}', [HesapController::class, 'aktivasyonFormu'])
        ->name('hesap.aktivasyon');
    Route::post('/hesap/aktivasyon/{kullanici}', [HesapController::class, 'aktivasyonKaydet'])
        ->name('hesap.aktivasyon.kaydet');
});

/*
 * Evrak görüntüleme. Yetki policy'de, şifre çözme sunucuda, erişim denetim
 * kaydında. Rota ULID ile bağlanır — sıralı id adreste görünmez.
 */
Route::middleware(['auth', 'throttle:evrak-goruntule'])->group(function () {
    Route::get('/evrak/{evrak}', [EvrakController::class, 'goster'])->name('evrak.goster');
    Route::get('/kart/{kart}/gorsel', [EvrakController::class, 'kartGorseli'])->name('kart.gorsel');
    Route::get('/icerik/{yol}', [EvrakController::class, 'icerikDosyasi'])
        ->where('yol', '(duyuru|bulten)/[\w.\-]+')->name('icerik.dosya');
});

/*
 * Kapı uygulaması (PWA) -- Plan v1.0 md.6.
 * Sayfanın KENDİSİ herkese açık; içerideki her işlem cihaz anahtarı ister.
 * Anahtarsız açan biri yalnızca boş bir kurulum ekranı görür.
 */
Route::get('/kapi', [KapiController::class, 'uygulama'])->name('kapi.uygulama');
Route::get('/kapi/manifest.json', [KapiController::class, 'manifest'])->name('kapi.manifest');
Route::get('/kapi/sw.js', [KapiController::class, 'serviceWorker'])->name('kapi.sw');
