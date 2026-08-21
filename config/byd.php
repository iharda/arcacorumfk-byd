<?php

/*
 * BYD'ye özel ayarlar. Kod içinde sabit yol/anahtar YOK — taşınabilirlik şartı
 * (müşterinin sunucusuna devredilecek).
 */
return [
    'evrak_disk' => env('BYD_EVRAK_DISK', 'evrak'),
    'kart_disk' => env('BYD_KART_DISK', 'kart'),

    /*
     * Yetkili panelinde iki adımlı doğrulama zorunluluğu.
     * Plan v1.0 md.11 bunu ZORUNLU sayıyor; kapatmak yalnızca geliştirme ve
     * deneme içindir. Kapalıyken 2FA'sını kurmuş kullanıcılardan yine kod
     * istenir — sadece "kurmadan giremezsin" dayatması kalkar.
     */
    '2fa_zorunlu' => (bool) env('BYD_2FA_ZORUNLU', true),

    'qr' => [
        'anahtar_surumu' => (int) env('BYD_QR_ANAHTAR_SURUMU', 1),
        'anahtarlar' => array_filter([
            1 => env('BYD_QR_ANAHTAR_V1'),
            2 => env('BYD_QR_ANAHTAR_V2'),
        ]),
    ],

    // Başsız Chrome — kart PDF/görsel üretimi. Yol .env'den; kodda sabit YOK.
    'chrome' => [
        'yol' => env('BYD_CHROME'),
        'node' => env('BYD_NODE', '/usr/bin/node'),
        'npm' => env('BYD_NPM', '/usr/bin/npm'),
        'modüller' => base_path('node_modules'),
    ],

    'kart' => [
        // Dikey rozet: telefonda okunur, A4'e sığar, yaka kartı ölçüsüne yakın.
        'genislik_mm' => 90,
        'yukseklik_mm' => 130,
    ],

    // Yükleme sınırları -- php-fpm havuzundaki upload_max_filesize'ı AŞMAMALI (16M)
    'yukleme' => [
        'maks_kb' => 8192,
        'mime_izin' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],
];
