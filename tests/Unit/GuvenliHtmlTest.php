<?php

namespace Tests\Unit;

use App\Servisler\GuvenliHtml;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Zengin metin saflaştırma -- Düzeltme listesi md.2. */
class GuvenliHtmlTest extends TestCase
{
    public function test_betik_ve_olay_nitelikleri_atilir(): void
    {
        foreach ([
            '<script>alert(1)</script>',
            '<SCRIPT >alert(1)</SCRIPT>',
            '<img src=x onerror="alert(1)">',
            '<svg onload=alert(1)></svg>',
            '<iframe src="//kotu.site"></iframe>',
            '<object data="x.swf"></object>',
        ] as $ham) {
            $temiz = (string) GuvenliHtml::temizle($ham);

            $this->assertStringNotContainsStringIgnoringCase('script', $temiz, "Sızdı: {$ham}");
            $this->assertStringNotContainsStringIgnoringCase('onerror', $temiz, "Sızdı: {$ham}");
            $this->assertStringNotContainsStringIgnoringCase('onload', $temiz, "Sızdı: {$ham}");
            $this->assertStringNotContainsStringIgnoringCase('iframe', $temiz, "Sızdı: {$ham}");
        }

        $this->assertSame('<p>olay</p>', GuvenliHtml::temizle('<p onclick="kotu()">olay</p>'));
    }

    public function test_arac_cubugu_bicimleri_korunur(): void
    {
        $ham = '<p>düz <strong>kalın</strong> <em>eğik</em></p>'
            .'<ul><li>madde</li></ul><ol><li>sıra</li></ol>'
            .'<h3>başlık</h3><blockquote>alıntı</blockquote>';

        $this->assertSame($ham, GuvenliHtml::temizle($ham));
    }

    public function test_javascript_semasi_dusurulur_http_kalir(): void
    {
        $this->assertStringNotContainsString('javascript:',
            (string) GuvenliHtml::temizle('<a href="javascript:alert(1)">tıkla</a>'));

        $temiz = (string) GuvenliHtml::temizle('<a href="https://ornek.com">bağ</a>');

        $this->assertStringContainsString('href="https://ornek.com"', $temiz);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $temiz);
    }

    /**
     * 🪤 Varsayılan eylem DROP olsaydı beyaz listede olmayan bir sarmal
     * (yapıştırılan `<div>`) içindeki METNİ de silerdi. BLOCK'a çekildi.
     */
    public function test_bilinmeyen_sarmal_metni_yok_etmez(): void
    {
        $this->assertStringContainsString('sarmalanmış metin',
            (string) GuvenliHtml::temizle('<div class="x"><section>sarmalanmış metin</section></div>'));

        $this->assertStringContainsString('hücre',
            (string) GuvenliHtml::temizle('<table><tr><td>hücre</td></tr></table>'));
    }

    /** 💣 `<style>` gövdesi düz metin olarak sızıyordu; ön geçiş söküyor. */
    public function test_style_govdesi_metin_olarak_sizmaz(): void
    {
        $this->assertSame('', GuvenliHtml::temizle('<style>body{color:red}</style>'));
    }

    public function test_null_null_kalir(): void
    {
        $this->assertNull(GuvenliHtml::temizle(null));
    }

    /** 💣 Symfony varsayılanı 20.000 baytta SESSİZCE kırpıyordu. */
    public function test_uzun_metin_kirpilmaz(): void
    {
        $uzun = '<p>'.str_repeat('abcdefghij', 5_000).'</p>';   // ~50 KB

        $this->assertSame($uzun, GuvenliHtml::temizle($uzun));
    }

    public function test_sinir_asilirsa_sessiz_kirpma_yerine_istisna(): void
    {
        $this->expectException(RuntimeException::class);

        GuvenliHtml::temizle(str_repeat('a', 500_001));
    }

    public function test_duz_metin_etiketsiz_doner(): void
    {
        $this->assertSame('kalın metin',
            GuvenliHtml::duzMetin('<p><strong>kalın</strong> metin</p><script>alert(1)</script>'));
    }
}
