<?php

namespace App\Filament\Yonetim\Resources\DenetimKayitlari\Pages;

use App\Filament\Yonetim\Resources\DenetimKayitlari\DenetimKaydiResource;
use Filament\Resources\Pages\ListRecords;

class ListDenetimKayitlari extends ListRecords
{
    protected static string $resource = DenetimKaydiResource::class;

    public function getTitle(): string
    {
        return 'Denetim kaydı';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
