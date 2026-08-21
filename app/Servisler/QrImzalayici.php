<?php

namespace App\Servisler;

use App\Models\Akreditasyon;
use RuntimeException;

/**
 * QR yükü ve imzası -- Plan v1.0 md.6.
 *
 * 🔑 QR'da YETKİ TAŞINMAZ. İçinde yalnızca akreditasyonun referansı, anahtar
 * sürümü ve HMAC imzası var. Kimin nereye girebileceği HER OKUTMADA sunucudan
 * sorulur; bu yüzden iptal anında etkilidir ve kart geri toplanmaz.
 *
 * Biçim:  BYD1.<surum>.<ulid>.<imza>
 *   - BYD1  : yük biçimi sürümü (ileride değişirse eski kartlar tanınsın)
 *   - surum : imza ANAHTARI sürümü — rotasyonda eski kartlar doğrulanmaya
 *             devam eder (md.6 "imza versiyonu QR yüküne eklenir")
 *   - imza  : HMAC-SHA256(ulid + surum), base64url, 22 karaktere kısaltılmış
 *             (128 bit — kaba kuvvete fazlasıyla yeter, QR küçük kalır)
 */
class QrImzalayici
{
    private const BICIM = 'BYD1';

    private const IMZA_UZUNLUK = 22;

    public function yukUret(Akreditasyon $akreditasyon, ?int $anahtarSurumu = null): string
    {
        $surum = $anahtarSurumu ?? (int) config('byd.qr.anahtar_surumu');
        $ulid = $akreditasyon->ulid;

        return implode('.', [self::BICIM, $surum, $ulid, $this->imzala($ulid, $surum)]);
    }

    /**
     * Yükü çözer. İmza tutmuyorsa null döner — çağıran taraf bunu
     * "imza geçersiz" olarak loglar.
     *
     * @return array{ulid: string, surum: int}|null
     */
    public function coz(string $yuk): ?array
    {
        $parca = explode('.', trim($yuk));

        if (count($parca) !== 4 || $parca[0] !== self::BICIM) {
            return null;
        }

        [, $surum, $ulid, $imza] = $parca;

        if (! ctype_digit($surum) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid)) {
            return null;
        }

        $beklenen = $this->imzala($ulid, (int) $surum, sessiz: true);

        // ⏱️ Sabit süreli karşılaştırma: imza tahmini zamanlamadan sızmasın.
        if ($beklenen === null || ! hash_equals($beklenen, $imza)) {
            return null;
        }

        return ['ulid' => $ulid, 'surum' => (int) $surum];
    }

    private function imzala(string $ulid, int $surum, bool $sessiz = false): ?string
    {
        $anahtar = config('byd.qr.anahtarlar')[$surum] ?? null;

        if (blank($anahtar)) {
            if ($sessiz) {
                return null;   // bilinmeyen anahtar sürümü = geçersiz imza
            }

            throw new RuntimeException("QR imza anahtarı tanımsız: sürüm {$surum}");
        }

        $ham = hash_hmac('sha256', $ulid . '|' . $surum, $anahtar, binary: true);

        return substr(rtrim(strtr(base64_encode($ham), '+/', '-_'), '='), 0, self::IMZA_UZUNLUK);
    }
}
