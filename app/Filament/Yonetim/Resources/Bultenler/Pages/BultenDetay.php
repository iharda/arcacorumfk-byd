<?php

namespace App\Filament\Yonetim\Resources\Bultenler\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Bultenler\BultenResource;
use App\Models\Bulten;

/**
 * Bülten detayı -- S1. Duyurunun aynısı, ek listesiyle (S3).
 *
 * Ek yerleşimi üye paneliyle AYNI bileşenden geliyor; biri düzeltilip diğeri
 * unutulmasın.
 */
class BultenDetay extends DetaySayfasi
{
    protected static string $resource = BultenResource::class;

    protected static ?string $title = 'Basın bülteni';

    public function kimlik(): string
    {
        return $this->kayit()->baslik;
    }

    public function durumRozeti(): ?array
    {
        return $this->kayit()->yayinda
            ? ['etiket' => 'Yayında', 'renk' => 'success']
            : ['etiket' => 'Taslak', 'renk' => 'gray'];
    }

    public function kunye(): array
    {
        $b = $this->kayit();

        return [
            'Yayın tarihi' => $b->yayin_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i'),
            'Oluşturan' => $b->olusturan?->name,
            'Bildirim' => $b->bildirim_gonderildi ? 'Gönderildi' : 'Gönderilmedi',
            'Ek sayısı' => count($b->ekler ?? []) ?: null,
        ];
    }

    public function sekmeler(): array
    {
        $b = $this->kayit();

        return [
            'onizleme' => [
                'baslik' => 'Üyenin göreceği hâli',
                'view' => 'filament.yonetim.icerik.onizleme',
                'veri' => [
                    'baslik' => $b->baslik,
                    'ozet' => null,
                    'icerik' => $b->icerik,
                    'ekler' => $b->ekler ?? [],
                    'yayinAt' => $b->yayin_at,
                ],
            ],
        ];
    }

    private function kayit(): Bulten
    {
        return $this->getRecord();
    }
}
