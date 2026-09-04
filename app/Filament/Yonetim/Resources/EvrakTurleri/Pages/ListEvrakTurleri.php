<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri\Pages;

use App\Filament\Yonetim\Resources\EvrakTurleri\EvrakTuruResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvrakTurleri extends ListRecords
{
    protected static string $resource = EvrakTuruResource::class;

    public function getTitle(): string
    {
        return 'Evrak türleri';
    }

    public function getSubheading(): ?string
    {
        return 'Başvuru formunda hangi belgelerin isteneceğini burası belirler. '
            .'Kayıtlar silinmez; kullanımdan kaldırmak için "Etkin" kapatılır.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni evrak türü'),
        ];
    }
}
