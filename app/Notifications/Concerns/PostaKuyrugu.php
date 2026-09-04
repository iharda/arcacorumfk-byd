<?php

namespace App\Notifications\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Giden postanın hız sınırı ve yeniden deneme kuralı -- tek yerde.
 *
 * 🔥 03.09.2026'da 34 bildirim ARKA ARKAYA düştü. Sebebi kod değil, hız:
 * `posta` kuyruğunda sekiz işçi aynı anda SMTP'ye yüklenince sağlayıcı
 * `451 4.7.1 Ratelimit "hostinger_out_ratelimit" exceeded` dedi. Üç deneme
 * de aynı saniyelere sıkıştığı için üçü de aynı duvara çarptı ve mailler
 * sessizce kayboldu.
 *
 * İki ayrı düzeltme var, karıştırılmasın:
 *
 *  1. HIZ SINIRI (`RateLimited`) -- sınıra HİÇ çarpmamak için. Kova dolduğunda
 *     iş kuyruğa geri bırakılır, hata sayılmaz.
 *  2. BACKOFF -- yine de bir SMTP hatası alınırsa denemeler 1 dk, 5 dk ve
 *     15 dk arayla yapılır; üçü de aynı ana sıkışmaz.
 *
 * 🪤 `RateLimited` işi `release()` ile geri bırakır ve bu, denemeyi ARTIRIR.
 * Bu yüzden `tries` yerine `retryUntil()` kullanılıyor: süre esaslı pencerede
 * geri bırakma sayısı sınırsızdır, `maxExceptions` ise GERÇEK hataları hâlâ
 * üçte keser. `tries` bırakılsaydı yoğun bir bülten gönderiminde iş, tek satır
 * hata almadan yalnızca sıra beklediği için "başarısız" olurdu.
 */
trait PostaKuyrugu
{
    /**
     * Gerçek hata (SMTP reddi, bağlantı kopması) bu kadar denenir.
     * Hız sınırı yüzünden geri bırakmalar buraya SAYILMAZ.
     */
    public int $maxExceptions = 3;

    /**
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        // Sınır SMTP içindir; veritabanı bildirimi kuyruğu meşgul etmesin.
        return $channel === 'mail' ? [new RateLimited('posta')] : [];
    }

    /**
     * Denemeler arası bekleme (saniye). Sağlayıcı sınırı dakikalıksa ilk
     * bekleme onu geçmeli, yoksa ikinci deneme de aynı kovaya düşer.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * Bu ana kadar gönderilemediyse pes edilir ve `failed_jobs`'a düşer.
     * Pencere dolmadan iş kaç kez geri bırakılırsa bırakılsın yaşamaya devam eder.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours((int) config('bys.posta.pes_etme_saati'));
    }
}
