<?php

namespace App\Filament\Yonetim\Resources\GecisKayitlari\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\GecisKayitlari\GecisKaydiResource;
use App\Models\Ayar;
use App\Models\GecisKaydi;

/**
 * Tek okutmanın tam hikâyesi -- S1.
 *
 * Kişi, kart, kapı, yön, bölge, sonuç, sebep -- ve "bu karta ait DİĞER
 * okutmalar". Listede satır satır bakarken kaybolan bağlam burada duruyor:
 * bir geçiş reddedildiyse aynı kartın o günkü diğer denemeleri hemen altında.
 */
class GecisKaydiDetay extends DetaySayfasi
{
    protected static string $resource = GecisKaydiResource::class;

    protected static ?string $title = 'Geçiş kaydı';

    public function kimlik(): string
    {
        $g = $this->kayit();

        return $g->akreditasyon?->kart_no ?? $g->okunan_referans ?? 'Okutma #'.$g->id;
    }

    public function altBaslik(): ?string
    {
        $g = $this->kayit();

        return trim(($g->akreditasyon?->kullanici?->name ?? 'Tanınmayan kart').' · '
            .($g->okundu_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s') ?? ''));
    }

    public function durumRozeti(): ?array
    {
        $sonuc = $this->kayit()->sonuc;

        return ['etiket' => $sonuc->etiket(), 'renk' => $sonuc->renk()];
    }

    public function kunye(): array
    {
        $g = $this->kayit();
        $bolgeler = (array) Ayar::al('bolgeler', []);

        return [
            'Kart no' => ['deger' => $g->akreditasyon?->kart_no, 'kopyala' => true],
            'Kişi' => $g->akreditasyon?->kullanici?->name,
            'Kapı' => $g->kapiIstemcisi?->ad ?? $g->kapi_kodu,
            'Yön' => $g->yon === 'giris' ? 'Giriş' : 'Çıkış',
            'Bölge' => filled($g->bolge) ? ($bolgeler[$g->bolge] ?? $g->bolge) : null,
            'Sebep' => $g->sebep,
            // Tanınmayan kartta akreditasyon yok; okunan ham referans kalır.
            'Okunan referans' => $g->okunan_referans,
            'İstemci IP' => $g->ip,
        ];
    }

    public function sekmeler(): array
    {
        $g = $this->kayit();

        $digerleri = $g->akreditasyon_id === null
            ? collect()
            : GecisKaydi::query()
                ->with('kapiIstemcisi')
                ->where('akreditasyon_id', $g->akreditasyon_id)
                ->whereKeyNot($g->id)
                ->latest('okundu_at')
                ->limit(20)
                ->get();

        return [
            'digerleri' => [
                'baslik' => 'Bu karta ait diğer okutmalar',
                'rozet' => $digerleri->count() ?: null,
                'view' => 'filament.yonetim.gecis.digerleri',
                'veri' => ['okutmalar' => $digerleri, 'kartVar' => $g->akreditasyon_id !== null],
            ],
        ];
    }

    private function kayit(): GecisKaydi
    {
        return $this->getRecord();
    }
}
