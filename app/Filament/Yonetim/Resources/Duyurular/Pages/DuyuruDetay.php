<?php

namespace App\Filament\Yonetim\Resources\Duyurular\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Duyurular\DuyuruResource;
use App\Models\Duyuru;

/**
 * Duyuru detayı -- S1. Üyenin GÖRECEĞİ hâlin önizlemesi ve bildirim durumu.
 *
 * 🔑 Önizleme neden gerekli: içerik zengin metin ve medya taşıyor; yayına
 * basmadan önce "üye ne görecek" sorusunun cevabı listede yoktu.
 */
class DuyuruDetay extends DetaySayfasi
{
    protected static string $resource = DuyuruResource::class;

    protected static ?string $title = 'Duyuru';

    public function kimlik(): string
    {
        return $this->kayit()->baslik;
    }

    public function altBaslik(): ?string
    {
        return $this->kayit()->ozet;
    }

    public function durumRozeti(): ?array
    {
        return $this->kayit()->yayinda
            ? ['etiket' => 'Yayında', 'renk' => 'success']
            : ['etiket' => 'Taslak', 'renk' => 'gray'];
    }

    public function kunye(): array
    {
        $d = $this->kayit();

        return [
            'Yayın tarihi' => $d->yayin_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i'),
            'Oluşturan' => $d->olusturan?->name,
            'Bildirim' => $d->bildirim_gonderildi ? 'Gönderildi' : 'Gönderilmedi',
            'Görsel' => $d->gorsel_yolu ? 'Var' : null,
            'Video' => $d->video_yolu ? 'Var' : null,
        ];
    }

    public function sekmeler(): array
    {
        $d = $this->kayit();

        // Görsel ve video ek listesine dönüştürülüyor: aynı yerleşim (S3).
        $ekler = array_values(array_filter([$d->gorsel_yolu, $d->video_yolu]));

        return [
            'onizleme' => [
                'baslik' => 'Üyenin göreceği hâli',
                'view' => 'filament.yonetim.icerik.onizleme',
                'veri' => [
                    'baslik' => $d->baslik,
                    'ozet' => $d->ozet,
                    'icerik' => $d->icerik,
                    'ekler' => $ekler,
                    'yayinAt' => $d->yayin_at,
                ],
            ],
        ];
    }

    private function kayit(): Duyuru
    {
        return $this->getRecord();
    }
}
