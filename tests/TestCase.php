<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testlerin DOKUNMASINA izin verilen veritabanları. Başka bir ad
     * görülürse test hiç başlamaz.
     */
    private const IZINLI_VERITABANLARI = ['byd_test', ':memory:'];

    /**
     * 💀 EMNİYET KİLİDİ. `bootstrap/cache/config.php` varken `env()` hiç
     * okunmaz ve `phpunit.xml`'deki DB ayarları YOK SAYILIR; testler sessizce
     * GELİŞTİRME veritabanına (`byd`) bağlanır. `RefreshDatabase` orada bir
     * `migrate:fresh` çalıştırırsa gerçek başvurular, kartlar ve denetim
     * kayıtları gider.
     *
     * Bu kontrol olmasaydı hata "test kırıldı" diye değil, "veri kayboldu"
     * diye fark edilirdi.
     */
    /**
     * 🪤 Kontrol `setUp()`'ta OLAMAZ: `RefreshDatabase` de `parent::setUp()`
     * içinde çalışır ve `migrate:fresh`'i orada atar -- kilit ancak veritabanı
     * silindikten SONRA devreye girerdi. `refreshApplication()` uygulamayı
     * kurar, trait'ler ise ondan sonra çalışır: doğru an burası.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $veritabani = (string) config('database.connections.'.config('database.default').'.database');

        if (! in_array($veritabani, self::IZINLI_VERITABANLARI, true)) {
            throw new RuntimeException(sprintf(
                'GÜVENLİK DURDURMASI: testler "%s" veritabanına bağlandı. İzinli: %s. '
                .'Muhtemel sebep: bootstrap/cache/config.php. Çözüm: php artisan config:clear',
                $veritabani, implode(', ', self::IZINLI_VERITABANLARI),
            ));
        }
    }
}
