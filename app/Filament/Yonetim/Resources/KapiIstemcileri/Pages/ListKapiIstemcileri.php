<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri\Pages;

use App\Filament\Yonetim\Resources\KapiIstemcileri\KapiIstemcisiResource;
use Filament\Resources\Pages\ListRecords;

class ListKapiIstemcileri extends ListRecords
{
    protected static string $resource = KapiIstemcisiResource::class;

    public function getTitle(): string
    {
        return 'Kapılar';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
