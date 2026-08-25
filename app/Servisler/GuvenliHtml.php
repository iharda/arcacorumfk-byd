<?php

namespace App\Servisler;

use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Zengin metin saflaştırma -- Düzeltme listesi md.2 (depolanmış XSS).
 *
 * 🔑 SAFLAŞTIRMA KAYDETME ANINDA yapılır, okuma anında değil. Okumada
 * yapsaydık her sayfa görüntülemesinde bedeli öderdik ve `{!! !!}` basan
 * YENİ bir görünüm eklendiğinde koruma sessizce atlanırdı. Tek kapı:
 * `Duyuru`/`Bulten` mutator'ı ve `Ayar::yaz()`.
 *
 * 🪤 Saflaştırıcı `symfony/html-sanitizer` -- kod tabanına yeni paket
 * girmedi, `filament/support` zaten getiriyor.
 */
class GuvenliHtml
{
    /**
     * 🔒 Beyaz liste RichEditor araç çubuğuyla AYNI kümedir (bold, italic,
     * link, bulletList, orderedList, h3, blockquote) + yapıştırılan metinde
     * sık görülen eşdeğerleri. Araç çubuğuna yeni düğme eklenirse buraya da
     * eklenmeli, yoksa biçim sessizce düz metne düşer.
     */
    private const IZINLI = [
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'sub', 'sup',
        'ul', 'ol', 'li',
        'h2', 'h3', 'h4',
        'blockquote', 'code', 'pre',
    ];

    /**
     * 💣 Bu elemanlar İÇERİĞİYLE BİRLİKTE atılır. Varsayılan davranış
     * "block" (etiketi sök, metni koru) olduğu için burada sayılmayan bir
     * `<script>` gövdesi ekranda düz metin olarak görünürdü.
     */
    private const ATILAN = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'option',
        'template', 'noscript', 'base', 'link', 'meta',
        'svg', 'math', 'canvas', 'audio', 'video', 'source', 'track',
        'frame', 'frameset', 'img',
    ];

    /**
     * 💣 Symfony saflaştırıcısının varsayılan girdi sınırı 20.000 BAYT ve
     * aşan metni SESSİZCE KIRPAR. KVKK aydınlatma metni bunu rahatlıkla
     * aşar. Sınır yükseltildi; yine de aşılırsa sessizce kırpmak yerine
     * istisna fırlatıyoruz -- veri kaybı gürültüsüz olmamalı.
     */
    private const AZAMI_GIRDI = 500_000;

    private static ?HtmlSanitizer $saflastirici = null;

    public static function temizle(?string $ham): ?string
    {
        if ($ham === null) {
            return null;
        }

        if (strlen($ham) > self::AZAMI_GIRDI) {
            throw new RuntimeException(sprintf(
                'Metin saflaştırma sınırını aşıyor (%d bayt, sınır %d). Metni bölün.',
                strlen($ham), self::AZAMI_GIRDI
            ));
        }

        /*
         * 🪤 `dropElement('style')` CSS gövdesini atmıyor -- ayrıştırıcı
         * `<style>` içeriğini ham metin olarak taşıyor ve saflaştırıcıya
         * düz metin gibi görünüyor. Güvenlik açığı değil (çalışmaz, yalnızca
         * görünür) ama çirkin; ön geçişte söküyoruz. `<script>` de aynı
         * yoldan iki kez elenmiş olur.
         */
        $ham = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $ham) ?? $ham;

        return self::saflastirici()->sanitize($ham);
    }

    /** Metin gövdesi -- e-posta özetinde ve arama içeriğinde kullanılır. */
    public static function duzMetin(?string $ham): string
    {
        return trim(html_entity_decode(strip_tags((string) self::temizle($ham)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function saflastirici(): HtmlSanitizer
    {
        if (self::$saflastirici !== null) {
            return self::$saflastirici;
        }

        /*
         * 🪤 Varsayılan eylem DROP: beyaz listede olmayan eleman ÇOCUKLARIYLA
         * BİRLİKTE silinir. Yapıştırılan bir `<div>` sarmalı bütün paragrafı
         * yok ederdi. BLOCK'a çekildi: etiket sökülür, metin kalır.
         */
        $ayar = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->withMaxInputLength(self::AZAMI_GIRDI);

        foreach (self::IZINLI as $eleman) {
            $ayar = $ayar->allowElement($eleman);
        }

        foreach (self::ATILAN as $eleman) {
            $ayar = $ayar->dropElement($eleman);
        }

        $ayar = $ayar
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            // Dış bağlantı yeni sekmede ve referrer sızdırmadan açılır.
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank');

        return self::$saflastirici = new HtmlSanitizer($ayar);
    }
}
