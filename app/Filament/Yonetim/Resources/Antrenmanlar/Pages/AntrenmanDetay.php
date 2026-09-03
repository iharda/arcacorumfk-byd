<?php

namespace App\Filament\Yonetim\Resources\Antrenmanlar\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Antrenmanlar\AntrenmanResource;
use App\Models\Antrenman;

/**
 * Antrenman detayı -- S1. Tek seans, basına açıklık durumu, yayın geçmişi.
 *
 * ♿ Basına açıklık hem renk hem YAZI taşır: "Kapalı" bilgisi rengin tek
 * başına anlatabileceği bir şey değil.
 */
class AntrenmanDetay extends DetaySayfasi
{
    protected static string $resource = AntrenmanResource::class;

    protected static ?string $title = 'Antrenman';

    public function kimlik(): string
    {
        $a = $this->kayit();

        return $a->baslik ?: $a->baslangic_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' antrenmanı';
    }

    public function altBaslik(): ?string
    {
        return $this->kayit()->yer;
    }

    public function durumRozeti(): ?array
    {
        return $this->kayit()->basina_acik
            ? ['etiket' => 'Basına açık', 'renk' => 'success']
            : ['etiket' => 'Basına kapalı', 'renk' => 'danger'];
    }

    public function kunye(): array
    {
        $a = $this->kayit();
        $bicim = fn (?object $t) => $t?->timezone('Europe/Istanbul')->format('d.m.Y H:i');

        return [
            'Başlangıç' => $bicim($a->baslangic_at),
            'Bitiş' => $bicim($a->bitis_at),
            'Yer' => $a->yer,
            'Yayın durumu' => $a->yayinda ? 'Yayında' : 'Taslak',
            'Yayın tarihi' => $bicim($a->yayin_at),
            'Bildirim' => $a->bildirim_gonderildi ? 'Gönderildi' : 'Gönderilmedi',
            'Oluşturan' => $a->olusturan?->name,
            'Not' => $a->not,
        ];
    }

    private function kayit(): Antrenman
    {
        return $this->getRecord();
    }
}
