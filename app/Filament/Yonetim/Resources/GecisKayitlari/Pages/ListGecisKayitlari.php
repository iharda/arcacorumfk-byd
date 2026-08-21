<?php

namespace App\Filament\Yonetim\Resources\GecisKayitlari\Pages;

use App\Filament\Yonetim\Resources\GecisKayitlari\GecisKaydiResource;
use Filament\Resources\Pages\ListRecords;

class ListGecisKayitlari extends ListRecords
{
    protected static string $resource = GecisKaydiResource::class;

    public function getTitle(): string
    {
        return 'Geçiş kayıtları';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
