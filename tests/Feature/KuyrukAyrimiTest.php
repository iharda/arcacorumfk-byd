<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Jobs\KartUret;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\User;
use App\Notifications\BasvuruAlindi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kuyruk ayrımı -- Düzeltme listesi md.7.
 *
 * 💀 Tek `default` kuyruğu vardı: bir bültenin 500 e-postası, onaylanan
 * başvurunun KARTINI sıraya sokuyordu. Bu test iş türlerinin doğru kuyruğa
 * düştüğünü kanıtlar; yapılandırma dosyasına bakmak yetmez.
 */
class KuyrukAyrimiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kart_uretimi_kart_kuyruguna_duser(): void
    {
        Queue::fake();

        $kullanici = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test', 'password' => bcrypt('x'),
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $akreditasyon = Akreditasyon::create([
            'basvuru_id' => $basvuru->id,
            'kart_no' => '2026-B-0001',
            'yil' => 2026,
            'tur_kodu' => 'B',
            'sira' => 1,
            'kullanici_id' => $kullanici->id,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        KartUret::dispatch($akreditasyon);

        Queue::assertPushed(KartUret::class,
            fn (KartUret $is) => $is->queue === 'kart');
    }

    /** Bildirimler POSTA kuyruğunda; ağır kart işini beklemezler. */
    public function test_bildirimler_posta_kuyruguna_duser(): void
    {
        foreach ([new BasvuruAlindi(...$this->bildirimArgumanlari(BasvuruAlindi::class))] as $bildirim) {
            $this->assertSame('posta', $bildirim->viaQueues()['mail'] ?? null);
        }
    }

    /** Her kuyruklu bildirim `viaQueues()` tanımlamalı: biri unutulursa yakalanır. */
    public function test_tum_kuyruklu_bildirimler_posta_soyluyor(): void
    {
        $eksik = [];

        foreach (glob(app_path('Notifications/*.php')) as $dosya) {
            $sinif = 'App\\Notifications\\'.basename($dosya, '.php');

            if (! is_subclass_of($sinif, ShouldQueue::class)) {
                continue;
            }

            if (! method_exists($sinif, 'viaQueues')) {
                $eksik[] = $sinif;

                continue;
            }

            $ornek = (new \ReflectionClass($sinif))->newInstanceWithoutConstructor();

            if (($ornek->viaQueues()['mail'] ?? null) !== 'posta') {
                $eksik[] = $sinif;
            }
        }

        $this->assertSame([], $eksik,
            'Bu bildirimler posta kuyruğuna düşmüyor: '.implode(', ', $eksik));
    }

    /** Yapılandırmada üç ayrı süpervizör tanımlı ve hepsinin tries > 1. */
    public function test_horizon_uc_kuyruk_ve_yeniden_deneme(): void
    {
        $sup = config('horizon.defaults');

        $this->assertSame(['kart'], $sup['supervisor-kart']['queue']);
        $this->assertSame(['posta'], $sup['supervisor-posta']['queue']);
        $this->assertSame(['default'], $sup['supervisor-default']['queue']);

        foreach ($sup as $ad => $ayar) {
            $this->assertGreaterThan(1, $ayar['tries'],
                "{$ad}: tries 1 kalırsa geçici SMTP hatası kalıcı kayıp bildirim demek.");
        }

        // Chrome iki render yapıyor: 60 sn yetmiyordu.
        $this->assertGreaterThanOrEqual(300, $sup['supervisor-kart']['timeout']);
        $this->assertGreaterThanOrEqual(512, $sup['supervisor-kart']['memory']);
    }

    /** @return array<int, mixed> */
    private function bildirimArgumanlari(string $sinif): array
    {
        return [Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_eposta' => 'aday@ornek.test',
        ])];
    }
}
