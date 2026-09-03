<?php

namespace App\Filament\Yonetim\Resources\DenetimKayitlari\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\DenetimKayitlari\DenetimKaydiResource;
use App\Models\DenetimKaydi;
use Illuminate\Database\Eloquent\Collection;

/**
 * Denetim kaydı ayrıntısı -- S1.
 *
 * 🔗 Eskiden `denetim-ayrinti` görünümü VARDI ama modalContent içindeydi:
 * kalıcı adresi yoktu, paylaşılamıyordu. Bir denetim kaydını e-postayla
 * göstermek ya da bir olaya bağlantı vermek mümkün değildi. Aynı içerik artık
 * kendi adresinde.
 *
 * ⚠️ Bu sayfada denetim izi bölümü YOK: kaydın kendisi zaten denetim kaydı,
 * kendi kendine iz tutmaz.
 */
class DenetimKaydiDetay extends DetaySayfasi
{
    protected static string $resource = DenetimKaydiResource::class;

    protected static ?string $title = 'Denetim kaydı';

    public function kimlik(): string
    {
        return $this->kayit()->olay;
    }

    public function altBaslik(): ?string
    {
        $k = $this->kayit();

        return trim(($k->kayit_etiketi ?? '').' · '
            .($k->created_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s') ?? ''), ' ·');
    }

    public function kunye(): array
    {
        $k = $this->kayit();

        return [
            'Olay' => ['deger' => $k->olay, 'kopyala' => true],
            'Kim' => $k->aktor_ad ?: ucfirst($k->aktor_tip),
            'Kayıt' => $k->kayit_etiketi,
            'Tip' => $k->kayit_tipi ? class_basename($k->kayit_tipi) : null,
            'Zaman' => $k->created_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s'),
            'IP' => $k->ip,
            'Not' => $k->not,
        ];
    }

    public function sekmeler(): array
    {
        $k = $this->kayit();

        return [
            'degisim' => [
                'baslik' => 'Değişim',
                'view' => 'filament.yonetim.denetim.degisim',
                'veri' => ['kayit' => $k],
            ],
        ];
    }

    /** Denetim kaydının kendi denetim izi olmaz. */
    public function denetimKayitlari(): Collection
    {
        return DenetimKaydi::query()->whereRaw('1 = 0')->get();
    }

    private function kayit(): DenetimKaydi
    {
        return $this->getRecord();
    }
}
