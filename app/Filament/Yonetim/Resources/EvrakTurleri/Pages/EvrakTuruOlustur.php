<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri\Pages;

use App\Filament\Yonetim\Resources\EvrakTurleri\EvrakTuruResource;
use App\Filament\Yonetim\Resources\EvrakTurleri\Schemas\EvrakTuruFormu;
use App\Servisler\DenetimYazici;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

/**
 * 📝 Yeni evrak türü DENETİME yazılır: bu ekran kamuya açık başvuru formunun
 * alanlarını değiştiriyor, "kim ne zaman ekledi" sorulabilmeli (M7.3).
 */
class EvrakTuruOlustur extends CreateRecord
{
    protected static string $resource = EvrakTuruResource::class;

    protected static ?string $title = 'Yeni evrak türü';

    public function form(Schema $schema): Schema
    {
        return $schema->components(EvrakTuruFormu::alanlar());
    }

    protected function getRedirectUrl(): string
    {
        return EvrakTuruResource::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $tur = $this->getRecord();

        app(DenetimYazici::class)->yaz('evrak_turu.eklendi', $tur,
            yeni: [
                'kod' => $tur->kod,
                'ad' => $tur->ad,
                'zorunlu' => $tur->zorunlu,
                'zorunlu_baslangic' => $tur->zorunlu_baslangic?->toDateString(),
                'basvuru_turleri' => $tur->basvuru_turleri,
            ]);
    }
}
