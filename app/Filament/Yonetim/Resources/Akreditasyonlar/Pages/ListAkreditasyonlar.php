<?php

namespace App\Filament\Yonetim\Resources\Akreditasyonlar\Pages;

use App\Filament\Yonetim\Resources\Akreditasyonlar\AkreditasyonResource;
use Filament\Resources\Pages\ListRecords;

class ListAkreditasyonlar extends ListRecords
{
    protected static string $resource = AkreditasyonResource::class;

    public function getTitle(): string
    {
        return 'Akreditasyonlar';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
