<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Pages;

use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use Filament\Resources\Pages\ListRecords;

class ListKurumlar extends ListRecords
{
    protected static string $resource = KurumResource::class;

    public function getTitle(): string
    {
        return 'Kurumlar';
    }

    // Kurum kaydı başvurudan doğar; elle oluşturma yok.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
