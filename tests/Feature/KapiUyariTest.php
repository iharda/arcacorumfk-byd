<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\GecisSonucu;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\GecisKaydi;
use App\Models\KapiIstemcisi;
use App\Models\User;
use App\Servisler\KapiDogrulama;
use Database\Seeders\AyarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kapı uyarıları -- Düzeltme listesi md.12.
 *
 * 💀 Kodun yorumu "geçişi ENGELLEMEZ" diyordu ama `basarili()` yalnızca
 * `Izinli`'ye evet dediği için görevli KIRMIZI RET EKRANI görüyordu.
 * Turnikeden geçen biri 30 saniye içinde ikinci kez okuttuğunda kırmızı
 * ekran çıkıyordu; maç günü bu, görevlinin sisteme güvenini bitirir.
 */
class KapiUyariTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AyarSeeder::class);
    }

    private function akreditasyon(): Akreditasyon
    {
        $kullanici = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test', 'password' => bcrypt('x'),
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        return Akreditasyon::create([
            'kart_no' => '2026-B-0001', 'yil' => 2026, 'tur_kodu' => 'B', 'sira' => 1,
            'kullanici_id' => $kullanici->id, 'basvuru_id' => $basvuru->id,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);
    }

    private function kapi(string $kod): KapiIstemcisi
    {
        return KapiIstemcisi::create([
            'ad' => 'Kapı '.$kod,
            'kapi_kodu' => $kod,
            'anahtar_onek' => 'kapi_'.$kod,
            'anahtar_hash' => hash('sha256', 'x'.$kod),
            'aktif' => true,
        ]);
    }

    private function gecisYaz(Akreditasyon $a, KapiIstemcisi $k, GecisSonucu $sonuc, int $saniyeOnce): void
    {
        GecisKaydi::create([
            'akreditasyon_id' => $a->id,
            'kapi_istemcisi_id' => $k->id,
            'kapi_kodu' => $k->kapi_kodu,
            'yon' => 'giris',
            'sonuc' => $sonuc,
            'okundu_at' => now()->subSeconds($saniyeOnce),
            'created_at' => now()->subSeconds($saniyeOnce),
        ]);
    }

    /** 🔑 Uyarı GEÇİŞTİR: yeşil değil sarı, ama kırmızı ret DEĞİL. */
    public function test_uyarilar_gecis_sayilir(): void
    {
        $this->assertTrue(GecisSonucu::MukerrerOkutma->basarili());
        $this->assertTrue(GecisSonucu::MukerrerOkutma->uyariMi());
        $this->assertTrue(GecisSonucu::BaskaKapida->basarili());
        $this->assertTrue(GecisSonucu::BaskaKapida->uyariMi());

        // Gerçek retler uyarı DEĞİL.
        foreach ([GecisSonucu::Iptal, GecisSonucu::Askida, GecisSonucu::Bulunamadi,
            GecisSonucu::ImzaGecersiz, GecisSonucu::BolgeYetkisiYok] as $ret) {
            $this->assertFalse($ret->basarili(), $ret->value.' geçiş sayılmamalı.');
            $this->assertFalse($ret->uyariMi(), $ret->value.' uyarı değil, RET.');
        }

        // İzinli uyarı değil.
        $this->assertFalse(GecisSonucu::Izinli->uyariMi());
    }

    /** AYNI kapıda kısa süre içinde ikinci okutma: yinelenen okuma uyarısı. */
    public function test_ayni_kapida_yinelenen_okuma(): void
    {
        $a = $this->akreditasyon();
        $k = $this->kapi('A1');
        $this->gecisYaz($a, $k, GecisSonucu::Izinli, 5);

        $sonuc = $this->dogrula($a, $k);

        $this->assertSame(GecisSonucu::MukerrerOkutma, $sonuc);
        $this->assertTrue($sonuc->uyariMi());
    }

    /**
     * 💀 ESKİ SORGU yalnızca AYNI kapıya bakıyordu: kartı paylaşan iki kişi
     * farklı turnikelere giderse hiç yakalanmıyordu. Yorum "kart paylaşımını
     * yakalamanın en pratik yolu" diyordu ama sorgu bunu yapmıyordu.
     */
    public function test_baska_kapida_okutma_yakalanir(): void
    {
        $a = $this->akreditasyon();
        $k1 = $this->kapi('A1');
        $k2 = $this->kapi('B2');

        $this->gecisYaz($a, $k1, GecisSonucu::Izinli, 20);

        $sonuc = $this->dogrula($a, $k2);

        $this->assertSame(GecisSonucu::BaskaKapida, $sonuc,
            'Kart başka kapıda okutulmuş; görevli uyarılmalı.');
        $this->assertTrue($sonuc->uyariMi(), 'Uyarı, ret değil.');
    }

    /** Süre geçtiyse uyarı yok: sıradan geçiş. */
    public function test_sure_gectiyse_uyari_yok(): void
    {
        $a = $this->akreditasyon();
        $k = $this->kapi('A1');
        $this->gecisYaz($a, $k, GecisSonucu::Izinli, 600);

        $this->assertSame(GecisSonucu::Izinli, $this->dogrula($a, $k));
    }

    /** Uyarı da "geçti" sayılır: iki uyarı üst üste gelirse ikincisi de uyarı. */
    public function test_uyari_kaydi_da_gecis_sayilir(): void
    {
        $a = $this->akreditasyon();
        $k = $this->kapi('A1');
        $this->gecisYaz($a, $k, GecisSonucu::MukerrerOkutma, 5);

        $this->assertSame(GecisSonucu::MukerrerOkutma, $this->dogrula($a, $k));
    }

    private function dogrula(Akreditasyon $a, KapiIstemcisi $k): GecisSonucu
    {
        $servis = app(KapiDogrulama::class);

        $mukerrer = (new \ReflectionMethod($servis, 'mukerrerMi'))->invoke($servis, $a, $k);
        $baska = (new \ReflectionMethod($servis, 'baskaKapidaMi'))->invoke($servis, $a, $k);

        return match (true) {
            $mukerrer => GecisSonucu::MukerrerOkutma,
            $baska => GecisSonucu::BaskaKapida,
            default => GecisSonucu::Izinli,
        };
    }
}
