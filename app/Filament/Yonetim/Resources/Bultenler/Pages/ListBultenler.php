<?php

namespace App\Filament\Yonetim\Resources\Bultenler\Pages;

use App\Filament\Yonetim\Resources\Bultenler\BultenResource;
use Filament\Resources\Pages\ListRecords;

class ListBultenler extends ListRecords
{
    protected static string $resource = BultenResource::class;

    public function getTitle(): string
    {
        return 'Basın bültenleri';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
