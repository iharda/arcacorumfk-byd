<?php

namespace Tests\Unit;

use App\Support\Telefon;
use PHPUnit\Framework\TestCase;

/** Telefon E.164 dönüşümü -- Revizyon md.5.2. */
class TelefonTest extends TestCase
{
    public function test_turkiye_numaralari_e164_olur(): void
    {
        $beklenen = '+905321234567';

        foreach ([
            '532 123 45 67',
            '0532 123 45 67',
            '05321234567',
            '+90 532 123 45 67',
            '905321234567',
            '0090 532 123 45 67',
            '(0532) 123-45-67',
        ] as $ham) {
            $this->assertSame($beklenen, Telefon::e164($ham, '+90'), "Dönüşmedi: {$ham}");
        }
    }

    public function test_sabit_hat_da_donusur(): void
    {
        $this->assertSame('+903642134567', Telefon::e164('0364 213 45 67', '+90'));
    }

    public function test_yabanci_numarada_ulke_kodu_secilene_gore(): void
    {
        $this->assertSame('+4915112345678', Telefon::e164('0151 12345678', '+49'));
        $this->assertSame('+4915112345678', Telefon::e164('+49 151 12345678', '+49'));
    }

    public function test_bos_deger_null_doner(): void
    {
        $this->assertNull(Telefon::e164('', '+90'));
        $this->assertNull(Telefon::e164(null, '+90'));
        $this->assertNull(Telefon::e164('   ', '+90'));
    }

    public function test_gosterim_bicimi(): void
    {
        $this->assertSame('+90 532 123 45 67', Telefon::goster('+905321234567'));
        $this->assertSame('+4915112345678', Telefon::goster('+4915112345678'));
        $this->assertSame('—', Telefon::goster(null));
        $this->assertSame('—', Telefon::goster(''));
    }

    /**
     * 💀 Düzeltme listesi md.1: toplu dönüşümde ülke kodu formdan gelmez.
     * `e164()` parametresizken yabancı numaraya `+90` yapıştırıyordu.
     */
    public function test_tr_e164_yabanci_numaraya_dokunmaz(): void
    {
        foreach ([
            '+49 170 1234567',      // Almanya
            '+44 20 7946 0958',     // İngiltere
            '+1 202 555 0173',      // ABD
        ] as $yabanci) {
            $this->assertNull(Telefon::trE164($yabanci), "Bozdu: {$yabanci}");
        }
    }

    public function test_tr_e164_uydurma_numara_uretmez(): void
    {
        foreach (['1234', 'dahili 145', '', 'yok', '0532 123'] as $bozuk) {
            $this->assertNull(Telefon::trE164($bozuk), "Uydurdu: {$bozuk}");
        }
    }

    public function test_tr_e164_gecerli_tr_numarasini_cevirir(): void
    {
        foreach ([
            '+90 532 123 45 67' => '+905321234567',
            '0532 123 45 67' => '+905321234567',
            '0364 213 45 67' => '+903642134567',
            '+905321234567' => '+905321234567',
        ] as $ham => $beklenen) {
            $this->assertSame($beklenen, Telefon::trE164($ham), "Çevrilmedi: {$ham}");
        }
    }
}
