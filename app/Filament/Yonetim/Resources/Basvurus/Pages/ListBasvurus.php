<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Pages;

use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListBasvurus extends ListRecords
{
    protected static string $resource = BasvuruResource::class;

    public function getTitle(): string
    {
        return 'Başvurular';
    }

    /**
     * Sayfa açıklaması -- Cüneyt Bey revizyonu (03.09.2026). Kuyruğun ne
     * içerdiğini başlığın altında söylüyor; yetkili "burada hangi başvurular
     * var" diye filtreleri kurcalamıyor.
     */
    public function getSubheading(): ?string
    {
        $temel = 'Medya kuruluşu, basın mensubu ve bağımsız içerik üreticisi '
            .'başvurularını inceleyin ve yönetin.';

        /*
         * 💀 M1-B: varsayılan durum süzgeci KUYRUK'tur; Onaylandı, Reddedildi,
         * İptal edildi ve Taslak listede HİÇ görünmez. Kullanıcının "kurumlarda
         * var, başvurularda yok" dediği tablonun ikinci yarısı buydu -- süzgeç
         * doğru çalışıyordu ama kendini açık etmiyordu.
         *
         * Süzgeç KALIYOR (kuyruk günlük iş); yalnızca görünür oluyor.
         */
        return $this->durumSuzgeciAcikMi()
            ? $temel.' · Şu anda yalnızca seçili durumlar gösteriliyor; '
                .'karara bağlananlar için "Tümünü göster".'
            : $temel;
    }

    // Başvuru yetkili tarafından OLUŞTURULMAZ -- "Yeni" düğmesi yok.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('tumunuGoster')
                ->label('Tümünü göster')
                ->icon('heroicon-m-funnel')
                ->color('gray')
                ->visible(fn () => $this->durumSuzgeciAcikMi())
                // Filament'in kendi API'si: süzgeci sıfırlar ve sayfayı başa alır.
                ->action(fn () => $this->removeTableFilter('durum')),
        ];
    }

    /** Durum süzgeci hâlâ bir alt kümeyi mi gösteriyor? */
    private function durumSuzgeciAcikMi(): bool
    {
        return filled($this->tableFilters['durum']['values'] ?? []);
    }
}
