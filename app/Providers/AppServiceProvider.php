<?php

namespace App\Providers;

use App\Http\Responses\PanelGirisYaniti;
use App\Listeners\GonderilemezAdresleriEngelle;
use App\Listeners\OturumOlaylariniKaydet;
use App\Models\Antrenman;
use App\Models\Bulten;
use App\Models\Duyuru;
use App\Policies\IcerikPolicy;
use Carbon\Carbon;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Girişten sonraki yönlendirme: kullanıcı KENDİ paneline gitsin.
         * Gerekçesi PanelGirisYaniti'nin başında yazılı (paneller arası
         * `url.intended` sızması → 403).
         */
        $this->app->bind(LoginResponse::class, PanelGirisYaniti::class);
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
        $this->oturumOlaylari();
    }

    /** Giriş / çıkış / başarısız deneme denetim kaydına düşer (md.10). */
    private function oturumOlaylari(): void
    {
        Event::listen(Login::class, [OturumOlaylariniKaydet::class, 'girdi']);
        Event::listen(Logout::class, [OturumOlaylariniKaydet::class, 'cikti']);
        Event::listen(Failed::class, [OturumOlaylariniKaydet::class, 'basarisiz']);
        Event::listen(Lockout::class, [OturumOlaylariniKaydet::class, 'kilitlendi']);
        Event::listen(PasswordReset::class, [OturumOlaylariniKaydet::class, 'sifreSifirlandi']);

        // Ayrılmış (.test/.invalid/.example) uzantılara gönderim yapma:
        // yalnızca geri dönüş üretir ve gönderen itibarını yıpratır.
        Event::listen(MessageSending::class, [GonderilemezAdresleriEngelle::class, 'handle']);
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

        // Düzeltme bağlantısı: davetle aynı mantık; dosya yüklemesi olduğu için
        // biraz daha dar.
        RateLimiter::for('basvuru-duzelt', fn (Request $r) => Limit::perMinutes(10, 15)->by($r->ip()));

        /*
         * Turnike doğrulaması. Maç günü kısa sürede çok okutma olur; sınır
         * İSTEMCİ BAŞINA konur, kapılar birbirini kilitlemesin.
         * ⚠️ Plan v1.0'daki "1.000 istek/sn" hedefi basın için fazlasıyla
         * yüksek; kapsam netleşince bu sayı birlikte gözden geçirilecek.
         */
        RateLimiter::for('kapi', function (Request $r) {
            /*
             * 💥 SIRALAMAYA GÜVENME. Laravel ara katmanları öncelik listesine
             * göre sıralar; `throttle` bizim kimlik doğrulamamızdan ÖNCE
             * çalışıyor ve o an `kapi_istemcisi` henüz atanmamış oluyor.
             * Eskiden IP'ye düşüyordu: BÜTÜN KAPILAR TEK SAYACI PAYLAŞIYORDU —
             * maç gününde yoğun bir kapı diğerlerini kilitlerdi.
             *
             * Çözüm: kovayı anahtarın ÖNEKİNDEN türet. Önek gizli değil
             * (veritabanında kaydı bulmak için zaten böyle duruyor) ama cihazı
             * tekil olarak tanımlıyor ve ara katman sırasından bağımsız.
             */
            $istemci = $r->attributes->get('kapi_istemcisi');
            $onek = substr((string) $r->header('X-Kapi-Anahtar'), 0, 12);

            $kova = match (true) {
                (bool) $istemci?->id => 'istemci:'.$istemci->id,
                $onek !== '' => 'onek:'.$onek,
                default => 'ip:'.$r->ip(),
            };

            return Limit::perMinute(600)->by($kova);
        });

        // Evrak görüntüleme: inceleme ekranı hızlı gezinir, bol bırakılır.
        RateLimiter::for('evrak-goruntule', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
