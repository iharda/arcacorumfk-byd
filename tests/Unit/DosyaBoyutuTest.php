<?php

namespace Tests\Unit;

use App\Support\DosyaBoyutu;
use PHPUnit\Framework\TestCase;

class DosyaBoyutuTest extends TestCase
{
    public function test_bayt_tam_sayi_kalir(): void
    {
        $this->assertSame('0 B', DosyaBoyutu::metin(0));
        $this->assertSame('820 B', DosyaBoyutu::metin(820));
        $this->assertSame('1.023 B', DosyaBoyutu::metin(1023));
    }

    /** 🇹🇷 Ondalık ayırıcı virgül, binlik ayırıcı nokta. */
    public function test_ust_birimler_tek_ondalikla_ve_virgulle_yazilir(): void
    {
        $this->assertSame('1,0 KB', DosyaBoyutu::metin(1024));
        $this->assertSame('1,4 MB', DosyaBoyutu::metin(1_468_006));
        $this->assertSame('2,5 GB', DosyaBoyutu::metin(2_684_354_560));
    }

    public function test_bilinmeyen_boyut_tire_olur(): void
    {
        $this->assertSame('—', DosyaBoyutu::metin(null));
        $this->assertSame('—', DosyaBoyutu::metin(-1));
    }

    /** En üst birimde kalır, "1024 TB" diye tasmaz. */
    public function test_en_ust_birimde_durur(): void
    {
        $this->assertStringEndsWith(' TB', DosyaBoyutu::metin(1024 ** 5));
    }
}
