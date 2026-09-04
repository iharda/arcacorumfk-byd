<?php

namespace Tests\Feature;

use App\Models\Basvuru;
use App\Notifications\BasvuruAlindi;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Giden postanın hız sınırı -- 03.09.2026'da 34 bildirimi düşüren hatanın testi.
 *
 * 🔒 Korunan davranış: kova dolduğunda bildirim BAŞARISIZ OLMAZ, kuyruğa geri
 * bırakılır. Eskiden üç deneme de aynı saniyelere sıkışıyor, sağlayıcının
 * `451 Ratelimit` yanıtı üçünü birden yakıyor ve mail sessizce kayboluyordu.
 */
class PostaHiziTest extends TestCase
{
    /** Kova sunucu başına: anahtar sabit olmalı, kullanıcıya göre değişmemeli. */
    public function test_posta_sinirlayicisi_tanimli_ve_sunucu_basina(): void
    {
        $limitler = (RateLimiter::limiter('posta'))(null);

        $this->assertCount(2, $limitler, 'Dakikalık ve saatlik iki kova bekleniyor');
        $this->assertSame(config('bys.posta.dakikada'), $limitler[0]->maxAttempts);
        $this->assertSame(config('bys.posta.saatte'), $limitler[1]->maxAttempts);
        // Laravel anahtara kova ölçüsünü de ekler ("smtp:attempts:20:decay:60"),
        // önemli olan öneğin SABİT olması -- IP ya da kullanıcı girmemeli.
        $this->assertStringStartsWith('smtp', $limitler[0]->key, 'Kova IP/kullanıcı başına olmamalı');
        $this->assertNotSame($limitler[0]->key, $limitler[1]->key, 'İki kova aynı sayacı paylaşmamalı');
    }

    /** Sınır SMTP içindir; veritabanı bildirimi aynı kovadan içmemeli. */
    public function test_ara_katman_yalnizca_posta_kanalinda(): void
    {
        $bildirim = new BasvuruAlindi(new Basvuru);
        $alici = new \Illuminate\Notifications\AnonymousNotifiable;

        $this->assertInstanceOf(RateLimited::class, $bildirim->middleware($alici, 'mail')[0]);
        $this->assertSame([], $bildirim->middleware($alici, 'database'));
    }

    /**
     * En kritik madde: `tries` yerine SÜRE esaslı pencere. Yoksa yoğun bir
     * bülten gönderiminde iş, tek satır hata almadan yalnızca sıra beklediği
     * için başarısız sayılırdı.
     */
    public function test_pencere_sure_esasli_ve_backoff_araliklari_aciliyor(): void
    {
        $bildirim = new BasvuruAlindi(new Basvuru);

        $this->assertSame([60, 300, 900], $bildirim->backoff());
        $this->assertSame(3, $bildirim->maxExceptions, 'Gerçek SMTP hatası hâlâ üçte kesilmeli');
        $this->assertEqualsWithDelta(
            now()->addHours(config('bys.posta.pes_etme_saati'))->timestamp,
            $bildirim->retryUntil()->getTimestamp(),
            5,
        );
    }

    /** Kova dolunca iş DÜŞMEZ, geri bırakılır. */
    public function test_kova_dolunca_is_geri_birakiliyor(): void
    {
        $sinir = config('bys.posta.dakikada');

        $isler = [];
        $calistir = function () use (&$isler) {
            $is = new class
            {
                public ?int $gerideBirakildi = null;

                public bool $calisti = false;

                public function release($saniye)
                {
                    $this->gerideBirakildi = (int) $saniye;
                }
            };
            (new RateLimited('posta'))->handle($is, function ($is) {
                $is->calisti = true;
            });
            $isler[] = $is;

            return $is;
        };

        for ($i = 0; $i < $sinir; $i++) {
            $this->assertTrue($calistir()->calisti, 'Sınır içindeki iş geçmeliydi');
        }

        $tasan = $calistir();
        $this->assertFalse($tasan->calisti, 'Sınırı aşan iş SMTP\'ye gitmemeliydi');
        $this->assertNotNull($tasan->gerideBirakildi, 'Aşan iş kuyruğa geri bırakılmalıydı');
        $this->assertGreaterThan(0, $tasan->gerideBirakildi);
    }
}
