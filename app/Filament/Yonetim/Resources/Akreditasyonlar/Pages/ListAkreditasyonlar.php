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

    /** Kurumlar ekranındaki açıklamanın diğer yarısı (M4.4 md.4). */
    public function getSubheading(): ?string
    {
        return 'Bu liste GERÇEK KİŞİ kartlarıdır: kurum çalışanları ve bağımsızlar. '
            .'Kurumların kendi akreditasyonu Kurumlar ekranındadır.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
