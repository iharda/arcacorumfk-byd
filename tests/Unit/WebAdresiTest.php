<?php

namespace Tests\Unit;

use App\Support\WebAdresi;
use PHPUnit\Framework\TestCase;

/**
 * Cüneyt Bey revizyonu (03.09.2026): "Bu şekilde kabul edilmeli,
 * http vs yazmaya zorlamamalıyız."
 */
class WebAdresiTest extends TestCase
{
    public function test_semasiz_adrese_https_eklenir(): void
    {
        $this->assertSame('https://aybers.com', WebAdresi::duzelt('aybers.com'));
        $this->assertSame('https://instagram.com/kullanici', WebAdresi::duzelt('instagram.com/kullanici'));
        $this->assertSame('https://www.corumhaber.com.tr', WebAdresi::duzelt('  www.corumhaber.com.tr  '));
    }

    public function test_mevcut_semaya_dokunulmaz(): void
    {
        $this->assertSame('http://aybers.com', WebAdresi::duzelt('http://aybers.com'));
        $this->assertSame('https://aybers.com', WebAdresi::duzelt('https://aybers.com'));
        $this->assertSame('https://aybers.com', WebAdresi::duzelt('//aybers.com'));
    }

    /** Bozuk şema `url` kuralına gitsin diye burada DÜZELTİLMEZ. */
    public function test_bilinmeyen_sema_oldugu_gibi_kalir(): void
    {
        $this->assertSame('mailto:a@b.com', WebAdresi::duzelt('mailto:a@b.com'));
        $this->assertSame('javascript:alert(1)', WebAdresi::duzelt('javascript:alert(1)'));
    }

    public function test_bos_ve_dizi_disi_degerler_bozulmaz(): void
    {
        $this->assertSame('', WebAdresi::duzelt(''));
        $this->assertSame('', WebAdresi::duzelt('   '));
        $this->assertNull(WebAdresi::duzelt(null));
        $this->assertSame(['x' => 'https://a.com', 'y' => ''], WebAdresi::dizi(['x' => 'a.com', 'y' => '']));
    }
}
