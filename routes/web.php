<?php

use App\Http\Controllers\BasvuruController;
use App\Http\Controllers\EvrakController;
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
Route::middleware(['auth', 'throttle:evrak-goruntule'])
    ->get('/evrak/{evrak}', [EvrakController::class, 'goster'])
    ->name('evrak.goster');
