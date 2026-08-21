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

    // Başvuru yetkili tarafından OLUŞTURULMAZ -- "Yeni" düğmesi yok.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
