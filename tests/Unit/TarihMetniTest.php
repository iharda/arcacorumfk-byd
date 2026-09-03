<?php

namespace Tests\Unit;

use App\Support\TarihMetni;
use PHPUnit\Framework\TestCase;

/**
 * Panodaki Türkçe yazım -- Cüneyt Bey revizyonu (03.09.2026):
 * "20 Ağustos 2026'da alındı" ve "2.7 gün" değil "2,7 gün".
 *
 * 🪤 Yıl eki RAKAMA değil OKUNUŞA bakar; aynı onlukta hem 'da hem 'te çıkar.
 */
class TarihMetniTest extends TestCase
{
    public function test_yil_eki_okunusa_gore_secilir(): void
    {
        $beklenen = [
            2024 => "'te",  // iki bin yirmi dört
            2025 => "'te",  // … beş
            2026 => "'da",  // … altı
            2027 => "'de",  // … yedi
            2029 => "'da",  // … dokuz
            2030 => "'da",  // … otuz
            2040 => "'ta",  // … kırk
            2000 => "'de",  // iki bin
        ];

        foreach ($beklenen as $yil => $ek) {
            $this->assertSame($ek, TarihMetni::yilEki($yil), "Yıl: {$yil}");
        }
    }

    public function test_ondalik_ayraci_virgul(): void
    {
        $this->assertSame('2,7', TarihMetni::sayi(2.7));
        $this->assertSame('0,3', TarihMetni::sayi(1 / 3));
        $this->assertSame('1.234,5', TarihMetni::sayi(1234.5));
    }
}
