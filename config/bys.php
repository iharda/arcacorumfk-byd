<?php

/*
 * BYS'ye özel ayarlar. Kod içinde sabit yol/anahtar YOK — taşınabilirlik şartı
 * (müşterinin sunucusuna devredilecek).
 */
return [
    'evrak_disk' => env('BYS_EVRAK_DISK', 'evrak'),
    'kart_disk' => env('BYS_KART_DISK', 'kart'),

    /*
     * Yetkili panelinde iki adımlı doğrulama zorunluluğu.
     * Plan v1.0 md.11 bunu ZORUNLU sayıyor; kapatmak yalnızca geliştirme ve
     * deneme içindir. Kapalıyken 2FA'sını kurmuş kullanıcılardan yine kod
     * istenir — sadece "kurmadan giremezsin" dayatması kalkar.
     */
    '2fa_zorunlu' => (bool) env('BYS_2FA_ZORUNLU', true),

    'qr' => [
        'anahtar_surumu' => (int) env('BYS_QR_ANAHTAR_SURUMU', 1),
        'anahtarlar' => array_filter([
            1 => env('BYS_QR_ANAHTAR_V1'),
            2 => env('BYS_QR_ANAHTAR_V2'),
        ]),
    ],

    // Başsız Chrome — kart PDF/görsel üretimi. Yol .env'den; kodda sabit YOK.
    'chrome' => [
        'yol' => env('BYS_CHROME'),
        'node' => env('BYS_NODE', '/usr/bin/node'),
        'npm' => env('BYS_NPM', '/usr/bin/npm'),
        'modüller' => base_path('node_modules'),
    ],

    'kart' => [
        // Dikey rozet: telefonda okunur, A4'e sığar, yaka kartı ölçüsüne yakın.
        'genislik_mm' => 90,
        'yukseklik_mm' => 130,
    ],

    /*
     * Giden posta hızı. Sağlayıcının (Hostinger) kendi sınırına ÇARPMADAN
     * kalmak için kova bizde tutulur -- 03.09'da sınıra çarpıp 34 bildirim
     * düşmüştü. Sayılar sağlayıcı planı değişirse birlikte güncellenmeli;
     * kova Redis'te olduğu için bütün işçiler AYNI sayacı paylaşır.
     */
    'posta' => [
        'dakikada' => (int) env('BYS_POSTA_DAKIKADA', 20),
        'saatte' => (int) env('BYS_POSTA_SAATTE', 400),
        // Bu süre boyunca gönderilemeyen bildirim başarısız sayılır.
        'pes_etme_saati' => (int) env('BYS_POSTA_PES_ETME_SAATI', 2),
    ],

    // Yükleme sınırları -- php-fpm havuzundaki upload_max_filesize'ı AŞMAMALI (16M)
    'yukleme' => [
        'maks_kb' => 8192,
        'mime_izin' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],
];
