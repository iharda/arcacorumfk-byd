<?php

namespace App\Filament\Yonetim\Resources\Duyurular\Pages;

use App\Filament\Yonetim\Resources\Duyurular\DuyuruResource;
use Filament\Resources\Pages\ListRecords;

class ListDuyurular extends ListRecords
{
    protected static string $resource = DuyuruResource::class;

    public function getTitle(): string
    {
        return 'Duyurular';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
