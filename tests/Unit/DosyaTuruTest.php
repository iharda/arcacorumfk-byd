<?php

namespace Tests\Unit;

use App\Support\DosyaTuru;
use PHPUnit\Framework\TestCase;

class DosyaTuruTest extends TestCase
{
    public function test_bilinen_uzantilar_mime_verir(): void
    {
        $this->assertSame('image/jpeg', DosyaTuru::mime('bulten/foto.JPG'));
        $this->assertSame('application/pdf', DosyaTuru::mime('bulten/rapor.pdf'));
        $this->assertSame('video/mp4', DosyaTuru::mime('duyuru/klip.mp4'));
    }

    /** Tanınmayan tür sessizce görsel sanılmamalı; S2 açık kart gösterecek. */
    public function test_bilinmeyen_uzanti_octet_stream_olur(): void
    {
        $this->assertSame('application/octet-stream', DosyaTuru::mime('bulten/dosya.xyz'));
        $this->assertSame('application/octet-stream', DosyaTuru::mime('bulten/uzantisiz'));
        $this->assertFalse(DosyaTuru::gorselMi('bulten/dosya.xyz'));
    }

    public function test_gorsel_ayrimi(): void
    {
        $this->assertTrue(DosyaTuru::gorselMi('bulten/a.png'));
        $this->assertTrue(DosyaTuru::gorselMi('bulten/a.webp'));
        $this->assertFalse(DosyaTuru::gorselMi('bulten/a.mp4'));
        $this->assertFalse(DosyaTuru::gorselMi('bulten/a.pdf'));
    }

    public function test_rozet_ve_ad(): void
    {
        $this->assertSame('XLSX', DosyaTuru::rozet('bulten/liste.xlsx'));
        $this->assertSame('DOSYA', DosyaTuru::rozet('bulten/uzantisiz'));
        $this->assertSame('liste.xlsx', DosyaTuru::ad('bulten/liste.xlsx'));
    }
}
