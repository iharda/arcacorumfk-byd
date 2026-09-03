<?php

namespace App\Support;

use App\Servisler\EvrakYukleyici;

/**
 * Dosya adından tür bilgisi -- ek listeleri (S3) ve önizleme (S2) için.
 *
 * 🪤 Diskten mimeType() SORULMUYOR: ek listesi bir sayfada onlarca satır
 * basıyor ve her satır için dosya sistemine gitmek gereksiz. Uzantı yeterli;
 * dosyanın kendisi zaten yükleme sırasında magic byte ile doğrulanmış
 * ({@see EvrakYukleyici}).
 */
class DosyaTuru
{
    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
    ];

    public static function uzanti(string $yol): string
    {
        return strtolower(pathinfo($yol, PATHINFO_EXTENSION));
    }

    public static function mime(string $yol): string
    {
        return self::MIME[self::uzanti($yol)] ?? 'application/octet-stream';
    }

    public static function gorselMi(string $yol): bool
    {
        return str_starts_with(self::mime($yol), 'image/');
    }

    /** Satır başındaki kısa rozet: PDF, XLSX, JPG… */
    public static function rozet(string $yol): string
    {
        $uzanti = self::uzanti($yol);

        return $uzanti === '' ? 'DOSYA' : mb_strtoupper($uzanti);
    }

    /** Ekranda gösterilecek ad: depodaki yol değil, dosyanın kendi adı. */
    public static function ad(string $yol): string
    {
        return basename($yol);
    }
}
