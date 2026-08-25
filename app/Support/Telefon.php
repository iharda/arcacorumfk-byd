<?php

namespace App\Support;

/**
 * Telefon biçimi -- Revizyon md.5.2.
 *
 * 🔑 SAKLAMA BİÇİMİ TEK: E.164 (`+905321234567`). Boşluksuz, parantezsiz.
 * Görüntüleme biçimi `goster()` ile ekranda üretilir — veritabanında biçimli
 * metin tutmak, arama ve karşılaştırmayı bozar (ValCert'te 2445 kaydı sonradan
 * düzeltmek zorunda kalmıştık).
 */
class Telefon
{
    /**
     * Ham girdiyi E.164'e çevirir. Ülke kodu form alanından gelir; kullanıcı
     * numaranın başına yine de `0`, `00CC` ya da `+CC` yazmış olabilir.
     */
    public static function e164(?string $ham, ?string $ulkeKodu = null): ?string
    {
        $ulke = preg_replace('/\D+/', '', $ulkeKodu ?: UlkeKodu::VARSAYILAN) ?: '90';
        $yerel = self::yerelRakamlar($ham, $ulkeKodu);

        return $yerel === '' ? null : '+'.$ulke.$yerel;
    }

    /**
     * Ülke kodu ve ulusal ön ek ayıklanmış abone numarası: `5321234567`.
     * Doğrulama kuralları bu sayı üzerinden çalışır.
     */
    public static function yerelRakamlar(?string $ham, ?string $ulkeKodu = null): string
    {
        $rakam = preg_replace('/\D+/', '', (string) $ham) ?? '';
        $ulke = preg_replace('/\D+/', '', $ulkeKodu ?: UlkeKodu::VARSAYILAN) ?: '90';

        if ($rakam === '') {
            return '';
        }

        // 00CC… ve CC… biçimleri: ülke kodu numaranın içine yazılmış.
        if (str_starts_with($rakam, '00'.$ulke)) {
            $rakam = substr($rakam, 2 + strlen($ulke));
        } elseif (str_starts_with($rakam, $ulke) && strlen($rakam) > strlen($ulke)) {
            $rakam = substr($rakam, strlen($ulke));
        }

        // Ulusal ön ek (Türkiye'de "0"): E.164'te yer almaz.
        return ltrim($rakam, '0');
    }

    /**
     * Ham değeri YALNIZCA Türkiye numarasına çevirir; çeviremiyorsa `null`.
     *
     * 💀 `e164()` ülke kodunu HER ZAMAN parametreden alır, girdideki `+49`'u
     * tanımaz. Ülke kodunun form alanından gelmediği yerlerde (toplu veri
     * dönüşümü) parametresiz çağırmak yabancı numaraya `+90` yapıştırır:
     * `+49 170 1234567` → `+90491701234567`. Bu metot o durumda dokunmaz.
     */
    public static function trE164(?string $ham): ?string
    {
        $bosluksuz = preg_replace('/\s+/', '', (string) $ham) ?? '';

        // Zaten E.164 ve TR DEĞİLSE yabancı numaradır.
        if (preg_match('/^\+(?!90)\d{6,15}$/', $bosluksuz)) {
            return null;
        }

        $yeni = self::e164($bosluksuz);

        // TR numarası +90 + 10 hane olmalı; değilse elde düzeltmeye bırakılır.
        return $yeni !== null && preg_match('/^\+90\d{10}$/', $yeni) ? $yeni : null;
    }

    /** Ekranda gösterilecek biçim. TR numaraları gruplanır, diğerleri olduğu gibi. */
    public static function goster(?string $e164): string
    {
        if (blank($e164)) {
            return '—';
        }

        $rakam = preg_replace('/\D+/', '', $e164) ?? '';

        if (str_starts_with($rakam, '90') && strlen($rakam) === 12) {
            $yerel = substr($rakam, 2);

            return sprintf('+90 %s %s %s %s',
                substr($yerel, 0, 3), substr($yerel, 3, 3), substr($yerel, 6, 2), substr($yerel, 8, 2));
        }

        return str_starts_with((string) $e164, '+') ? $e164 : '+'.$rakam;
    }
}
