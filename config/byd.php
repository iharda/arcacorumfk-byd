<?php

/*
 * BYD'ye özel ayarlar. Kod içinde sabit yol/anahtar YOK — taşınabilirlik şartı
 * (müşterinin sunucusuna devredilecek).
 */
return [
    'evrak_disk' => env('BYD_EVRAK_DISK', 'evrak'),
    'kart_disk'  => env('BYD_KART_DISK', 'kart'),

    'qr' => [
        'anahtar_surumu' => (int) env('BYD_QR_ANAHTAR_SURUMU', 1),
        'anahtarlar'     => array_filter([
            1 => env('BYD_QR_ANAHTAR_V1'),
            2 => env('BYD_QR_ANAHTAR_V2'),
        ]),
    ],

    // Yükleme sınırları -- php-fpm havuzundaki upload_max_filesize'ı AŞMAMALI (16M)
    'yukleme' => [
        'maks_kb'    => 8192,
        'mime_izin'  => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],
];
