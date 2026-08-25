<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Support\DuzeltmeAlanlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Düzeltme anahtarlarının kalıcılığı -- Düzeltme listesi md.11. */
class DuzeltmeAlanlariTest extends TestCase
{
    use RefreshDatabase;

    private function basvuru(): Basvuru
    {
        return Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::EksikEvrak,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);
    }

    private function evrakTuru(): EvrakTuru
    {
        return EvrakTuru::create([
            'kod' => 'kimlik_gorseli',
            'ad' => 'Kimlik / ehliyet / pasaport',
            'basvuru_turleri' => [BasvuruTuru::BasinMensubu->value],
            'zorunlu' => true,
            'maks_boyut_kb' => 4096,
            'hassas' => true,
            'sira' => 1,
            'aktif' => true,
        ]);
    }

    /**
     * 💀 ASIL HATA: anahtar görünen addı. Yetkili evrak türünün adını
     * değiştirince yoldaki biletlerde yükleme kutusu SESSİZCE kayboluyordu.
     */
    public function test_evrak_turunun_adi_degisince_bilet_bozulmaz(): void
    {
        $tur = $this->evrakTuru();
        $basvuru = $this->basvuru();

        $anahtar = DuzeltmeAlanlari::EVRAK_ONEK.$tur->kod;
        $basvuru->update(['duzeltme_notlari' => [$anahtar => 'Okunmuyor, yeniden yükleyin']]);

        // Yetkili adı değiştirdi.
        $tur->update(['ad' => 'Kimlik belgesi']);

        $this->assertTrue(
            DuzeltmeAlanlari::evrakIsteniyorMu($tur->fresh(), $basvuru->fresh()->duzeltilebilirAlanlar()),
            'Ad değişince evrak talebi kaybolmamalı.',
        );

        $this->assertSame('Kimlik belgesi', $basvuru->fresh()->duzeltmeEtiketi($anahtar),
            'Etiket saklanmaz, güncel addan üretilir.');
    }

    /** 🪤 Yolda olan eski biletlerde çıplak ad anahtarı var; hâlâ çalışmalı. */
    public function test_eski_ciplak_ad_anahtari_kabul_edilir(): void
    {
        $tur = $this->evrakTuru();
        $basvuru = $this->basvuru();
        $basvuru->update(['duzeltme_notlari' => ['Kimlik / ehliyet / pasaport' => 'eski bilet']]);

        $this->assertTrue(
            DuzeltmeAlanlari::evrakIsteniyorMu($tur, $basvuru->duzeltilebilirAlanlar()),
        );

        $this->assertSame('Kimlik / ehliyet / pasaport',
            $basvuru->duzeltmeEtiketi('Kimlik / ehliyet / pasaport'),
            'Tanınmayan anahtar olduğu gibi gösterilir, boş değil.');
    }

    public function test_veri_alanlari_onekli_ve_ture_gore(): void
    {
        $kurumsal = DuzeltmeAlanlari::veriAlanlari(BasvuruTuru::Kurum);
        $bireysel = DuzeltmeAlanlari::veriAlanlari(BasvuruTuru::BasinMensubu);

        $this->assertArrayHasKey('veri:vergi_no', $kurumsal);
        $this->assertArrayNotHasKey('veri:vergi_no', $bireysel);
        $this->assertArrayHasKey('veri:basin_karti', $bireysel);
        $this->assertArrayNotHasKey('veri:basin_karti', $kurumsal);

        foreach (array_keys($kurumsal + $bireysel) as $anahtar) {
            $this->assertStringStartsWith(DuzeltmeAlanlari::VERI_ONEK, $anahtar);
        }
    }

    public function test_evrak_mi_ayrimi(): void
    {
        $this->assertTrue(DuzeltmeAlanlari::evrakMi('evrak:kimlik_gorseli'));
        $this->assertFalse(DuzeltmeAlanlari::evrakMi('veri:telefon'));
        $this->assertFalse(DuzeltmeAlanlari::evrakMi('Sosyal medya'));
    }
}
