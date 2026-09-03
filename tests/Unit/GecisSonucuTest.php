<?php

namespace Tests\Unit;

use App\Enums\GecisSonucu;
use PHPUnit\Framework\TestCase;

class GecisSonucuTest extends TestCase
{
    /** 💀 Uyarı sonuçları KIRMIZI OLMAMALI: geçişi engellemiyorlar. */
    public function test_uyari_sonuclari_sari(): void
    {
        $this->assertSame('warning', GecisSonucu::MukerrerOkutma->renk());
        $this->assertSame('warning', GecisSonucu::BaskaKapida->renk());
    }

    public function test_izinli_yesil(): void
    {
        $this->assertSame('success', GecisSonucu::Izinli->renk());
    }

    public function test_engelleyen_sonuclar_kirmizi(): void
    {
        foreach ([GecisSonucu::Askida, GecisSonucu::Iptal, GecisSonucu::Bulunamadi,
            GecisSonucu::ImzaGecersiz, GecisSonucu::BolgeYetkisiYok] as $sonuc) {
            $this->assertSame('danger', $sonuc->renk(), $sonuc->value);
        }
    }

    /** Her sonucun rengi ve etiketi olmalı: yeni durum eklenirse yakalanır. */
    public function test_her_sonucun_rengi_ve_etiketi_var(): void
    {
        foreach (GecisSonucu::cases() as $sonuc) {
            $this->assertContains($sonuc->renk(), ['success', 'warning', 'danger'], $sonuc->value);
            $this->assertNotSame('', $sonuc->etiket(), $sonuc->value);
        }
    }
}
