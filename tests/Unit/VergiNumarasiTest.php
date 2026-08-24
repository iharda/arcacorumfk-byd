<?php

namespace Tests\Unit;

use App\Rules\VergiNumarasi;
use PHPUnit\Framework\TestCase;

/**
 * VKN / TCKN sağlama algoritması -- Revizyon md.5.3.
 *
 * 💀 Dolaşan VKN kopyalarının çoğu hatalı ("0000000000" geçiyor). Buradaki
 * geçerli örnekler İKİ bağımsız kütüphaneyle karşılaştırılarak seçildi;
 * algoritmayı değiştiren biri bu testi kırmadan geçemez.
 */
class VergiNumarasiTest extends TestCase
{
    private function gecerliMi(string $deger): bool
    {
        $hata = null;

        (new VergiNumarasi)->validate('vergi_no', $deger, function (string $mesaj) use (&$hata) {
            $hata = $mesaj;
        });

        return $hata === null;
    }

    public function test_gecerli_vkn_kabul_edilir(): void
    {
        foreach (['1430466081', '8790012345', '1234567890', '5486177004', '9734428737'] as $vkn) {
            $this->assertTrue($this->gecerliMi($vkn), "Geçerli VKN reddedildi: {$vkn}");
        }
    }

    public function test_gecersiz_vkn_reddedilir(): void
    {
        // 0000000000: hatalı kopyaların geçirdiği numara.
        foreach (['0000000000', '1111111111', '3044074351', '1430466080', '5486177005'] as $vkn) {
            $this->assertFalse($this->gecerliMi($vkn), "Geçersiz VKN kabul edildi: {$vkn}");
        }
    }

    public function test_gecerli_tckn_kabul_edilir(): void
    {
        // 29092509062: tek*7 - çift NEGATİF çıkan numara; modu normalize
        // etmeyen uygulamalar bunu yanlışlıkla reddediyor.
        foreach (['11111111110', '10000000146', '29092509062'] as $tckn) {
            $this->assertTrue($this->gecerliMi($tckn), "Geçerli TCKN reddedildi: {$tckn}");
        }
    }

    public function test_gecersiz_tckn_reddedilir(): void
    {
        foreach (['01111111110', '11111111111', '12345678901', '29092509063'] as $tckn) {
            $this->assertFalse($this->gecerliMi($tckn), "Geçersiz TCKN kabul edildi: {$tckn}");
        }
    }

    public function test_hane_sayisi_tutmayan_deger_reddedilir(): void
    {
        foreach (['', '143046608', '143046608123', 'abcdefghij'] as $deger) {
            $this->assertFalse($this->gecerliMi($deger), "Hatalı uzunluk kabul edildi: {$deger}");
        }
    }

    public function test_araya_giren_bosluk_ve_tire_temizlenir(): void
    {
        // Kopyala-yapıştırda numara biçimli gelir; kural rakam dışını atar.
        foreach (['14304 66081', '1430-466-081', ' 1430466081 '] as $deger) {
            $this->assertTrue($this->gecerliMi($deger), "Biçimli numara reddedildi: {$deger}");
        }
    }
}
