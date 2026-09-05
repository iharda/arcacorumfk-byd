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

    /**
     * 💀 BOŞ MODAL, BOŞ SATIR (İbrahim Bey, 05.09.2026).
     *
     * `CreateAction::make()` çıplak bırakılmıştı. Filament bu eylemi bir sayfaya
     * BAĞLAYAMADI çünkü oluşturma sayfası `olustur` anahtarıyla kayıtlı, o ise
     * `create` arıyor; şema da verilmediği ve `EvrakTuruResource`'ta `form()`
     * bulunmadığı için ALANSIZ bir modal açtı. Gönderilince doğrulanacak alan
     * olmadığından hiçbir uyarı çıkmıyor, doğrudan
     * `insert into evrak_turleri (created_at, updated_at)` çalışıyor ve
     * veritabanı `kod` NOT NULL diye reddediyordu. Yetkili "neden evrak türü
     * oluşturamıyorum" diyor, ekran ona hiçbir şey söylemiyordu.
     *
     * 🔑 Modal DEĞİL, sayfaya gidilir: `EvrakTuruOlustur` zaten yazılmış ve
     * `afterCreate()` içinde denetim kaydını yazıyor. Modale şema takmak o
     * denetim kaydını atlardı -- bu ekran kamuya açık başvuru formunu
     * değiştiriyor, "kim ekledi" kaybolamaz.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Yeni evrak türü')
                ->url(fn () => EvrakTuruResource::getUrl('olustur')),
        ];
    }
}
