<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Pages;

use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use Filament\Resources\Pages\ListRecords;

class ListBasvurus extends ListRecords
{
    protected static string $resource = BasvuruResource::class;

    public function getTitle(): string
    {
        return 'Başvurular';
    }

    /**
     * Sayfa açıklaması -- Cüneyt Bey revizyonu (03.09.2026).
     *
     * 🗑️ "Yalnızca kuyruktakiler gösteriliyor · Tümünü göster" uyarısı ve
     * düğmesi KALDIRILDI (05.09.2026 revizyonu): varsayılan durum süzgeci
     * artık yok, liste her zaman tüm başvuruları getiriyor. Açıklanacak bir
     * gizleme kalmadı.
     */
    public function getSubheading(): ?string
    {
        return 'Medya kuruluşu, basın mensubu ve bağımsız içerik üreticisi '
            .'başvurularını inceleyin ve yönetin.';
    }

    // Başvuru yetkili tarafından OLUŞTURULMAZ -- "Yeni" düğmesi yok.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
