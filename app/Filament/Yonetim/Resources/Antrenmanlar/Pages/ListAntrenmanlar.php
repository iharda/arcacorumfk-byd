<?php

namespace App\Filament\Yonetim\Resources\Antrenmanlar\Pages;

use App\Filament\Yonetim\Resources\Antrenmanlar\AntrenmanResource;
use Filament\Resources\Pages\ListRecords;

class ListAntrenmanlar extends ListRecords
{
    protected static string $resource = AntrenmanResource::class;

    public function getTitle(): string
    {
        return 'Antrenman takvimi';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
