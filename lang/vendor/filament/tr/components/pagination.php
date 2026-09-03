<?php

/*
 * Sayfalama metinleri -- Cüneyt Bey revizyonu (03.09.2026):
 * "3 başvurunun 1–3 arası gösteriliyor" ve "Sayfa başına".
 *
 * 🪤 Yalnızca buradaki anahtarlar ezilir; gerisi Filament'ten gelir.
 */
return [
    'overview' => ':total kaydın :first–:last arası gösteriliyor',

    'fields' => [
        'records_per_page' => [
            'label' => 'Sayfa başına',
        ],
    ],
];
