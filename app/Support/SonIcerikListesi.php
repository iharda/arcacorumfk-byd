<?php

namespace App\Support;

use App\Models\Antrenman;
use App\Models\Bulten;
use App\Models\Duyuru;
use Illuminate\Support\Collection;

/**
 * Duyuru / bülten / antrenman kayıtlarını tek akışa indirger.
 *
 * 🔑 Üç ayrı model, üç ayrı tarih alanı; panoda tek liste isteniyor. Normalize
 * etme işi burada tek yerde durur, panel widget'ları yalnızca gösterir.
 */
class SonIcerikListesi
{
    /**
     * @param  array<string, string>  $adresler  tip => bağlantı adresi
     * @return Collection<int, array<string, mixed>>
     */
    public static function son(int $adet, array $adresler = []): Collection
    {
        $duyurular = Duyuru::query()->yayinda()
            ->orderByDesc('yayin_at')->orderByDesc('id')->limit($adet)->get()
            ->map(fn (Duyuru $d) => [
                'tip' => 'duyuru',
                'etiket' => 'Duyuru',
                'baslik' => $d->baslik,
                'tarih' => $d->yayin_at ?? $d->created_at,
                'adres' => $adresler['duyuru'] ?? null,
                'renk' => 'info',
            ]);

        $bultenler = Bulten::query()->yayinda()
            ->orderByDesc('yayin_at')->orderByDesc('id')->limit($adet)->get()
            ->map(fn (Bulten $b) => [
                'tip' => 'bulten',
                'etiket' => 'Bülten',
                'baslik' => $b->baslik,
                'tarih' => $b->yayin_at ?? $b->created_at,
                'adres' => $adresler['bulten'] ?? null,
                'renk' => 'gray',
            ]);

        /*
         * Antrenmanda anlamlı tarih BAŞLANGIÇ zamanıdır (yayın tarihi değil):
         * "önümüzdeki cumartesi antrenman var" bilgisi listeye ona göre girer.
         */
        $antrenmanlar = Antrenman::query()->yayinda()
            ->orderByDesc('baslangic_at')->limit($adet)->get()
            ->map(fn (Antrenman $a) => [
                'tip' => 'antrenman',
                'etiket' => $a->basina_acik ? 'Antrenman · basına açık' : 'Antrenman',
                'baslik' => $a->baslik ?? 'Antrenman',
                'tarih' => $a->baslangic_at,
                'adres' => $adresler['antrenman'] ?? null,
                'renk' => $a->basina_acik ? 'success' : 'gray',
            ]);

        $satirlar = $duyurular
            ->concat($bultenler)
            ->concat($antrenmanlar)
            ->sortByDesc(fn (array $satir) => $satir['tarih']?->getTimestamp() ?? 0)
            ->take($adet)
            ->values();

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }
}
