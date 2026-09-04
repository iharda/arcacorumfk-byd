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

    /**
     * 🔑 Bilgi mimarisi EKRANDA anlatılır -- Tutarsızlık incelemesi M4.4 md.3.
     *
     * Modelleme doğru ama görünmez: kurumun akreditasyonu `kurumlar` tablosunda
     * bir SÜTUN, çalışanlarınki ayrı birer KART. "Kurum kartı" diye bir şey yok
     * ve AkreditasyonlarTable'daki "Üye türü" süzgeci `Kurum`u bilerek dışarıda
     * bırakıyor. Bunu bilmeyen yetkili kurumun akreditasyonunu Akreditasyonlar
     * ekranında arayıp bulamıyor.
     */
    public function getSubheading(): ?string
    {
        return 'Bu liste TÜZEL KİŞİ kayıtlarıdır; kurumun akreditasyonu burada bir '
            .'durum sütunudur. Çalışanların basın kartları Akreditasyonlar ekranındadır.';
    }

    // Kurum kaydı başvurudan doğar; elle oluşturma yok.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
