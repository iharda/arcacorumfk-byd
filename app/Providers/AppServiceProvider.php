<?php

namespace App\Providers;

use App\Models\Antrenman;
use App\Models\Bulten;
use App\Models\Duyuru;
use App\Policies\IcerikPolicy;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Tarih adları Türkçe olsun (Carbon::translatedFormat).
         * APP_LOCALE=tr tek başına yetmiyor; Carbon kendi dilini ayrıca ister —
         * yoksa takvimde ay adları "Aug" diye çıkar.
         */
        Carbon::setLocale(config('app.locale'));

        $this->hizSinirlari();
        $this->politikalar();
    }

    /**
     * Laravel policy'leri isim eşleşmesiyle bulur (Basvuru → BasvuruPolicy).
     * Duyuru/Antrenman/Bülten AYNI policy'yi paylaşıyor; o yüzden elle bağlanır.
     * Bağlanmazsa yetki kontrolü sessizce DEVRE DIŞI kalır.
     */
    private function politikalar(): void
    {
        foreach ([Duyuru::class, Antrenman::class, Bulten::class] as $model) {
            Gate::policy($model, IcerikPolicy::class);
        }
    }

    /**
     * Adlandırılmış hız sınırları.
     *
     * 🪤 `throttle:5,10` gibi ANONİM sınırlar, anahtarı rota URI'sinden üretir.
     * Aynı adreste GET ve POST varsa İKİSİ AYNI SAYACI paylaşır: formu birkaç
     * kez açmak, gönderme hakkını tüketir ve kullanıcı sessizce 429 alır.
     * Adlandırılmış sınır kendi anahtarını kullanır, bu çakışma olmaz.
     */
    private function hizSinirlari(): void
    {
        // Form sayfasını görüntüleme
        RateLimiter::for('basvuru-goruntule', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        // Başvuru gönderimi: hesap açar ve e-posta gönderir, dar tutulur.
        RateLimiter::for('basvuru-gonder', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));

        // Aktivasyon: imzalı bağlantı, yine de kaba kuvvete kapalı olsun.
        RateLimiter::for('hesap-aktivasyon', fn (Request $r) => Limit::perMinutes(10, 15)->by($r->ip()));

        // Davet bağlantısı: token tahmin edilemez ama yine de kaba kuvvete kapalı.
        RateLimiter::for('davet', fn (Request $r) => Limit::perMinutes(10, 20)->by($r->ip()));

        /*
         * Turnike doğrulaması. Maç günü kısa sürede çok okutma olur; sınır
         * İSTEMCİ BAŞINA konur, kapılar birbirini kilitlemesin.
         * ⚠️ Plan v1.0'daki "1.000 istek/sn" hedefi basın için fazlasıyla
         * yüksek; kapsam netleşince bu sayı birlikte gözden geçirilecek.
         */
        RateLimiter::for('kapi', fn (Request $r) => Limit::perMinute(600)
            ->by(optional($r->attributes->get('kapi_istemcisi'))->id ?: $r->ip()));

        // Evrak görüntüleme: inceleme ekranı hızlı gezinir, bol bırakılır.
        RateLimiter::for('evrak-goruntule', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
