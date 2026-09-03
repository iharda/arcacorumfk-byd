<?php

namespace App\Filament\Uye\Pages;

use App\Models\Akreditasyon;
use App\Support\KartDurumu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Üye paneli — "Kartım". Plan v1.0 md.8.
 * Kartı görüntüle + PDF indir. Kart yoksa (henüz üretilmediyse) sebebi yazılır.
 */
class Kartim extends Page
{
    protected string $view = 'filament.uye.kartim';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Kartım';

    protected static ?string $title = 'Basın kartım';

    protected static ?int $navigationSort = 0;

    public static function shouldRegisterNavigation(): bool
    {
        return Akreditasyon::where('kullanici_id', Auth::id())->exists();
    }

    public function getAkreditasyonProperty(): ?Akreditasyon
    {
        return Akreditasyon::with(['guncelKart', 'kurum'])
            ->where('kullanici_id', Auth::id())
            ->latest('id')
            ->first();
    }

    public function getGorselAdresiProperty(): ?string
    {
        $kart = $this->akreditasyon?->guncelKart;

        return $kart?->gorsel_yolu ? route('kart.gorsel', $kart) : null;
    }

    public function indirAction(): Action
    {
        return Action::make('indir')
            ->label('PDF indir')
            ->icon('heroicon-m-arrow-down-tray')
            /*
             * 🔒 `kart.indir` yetkisi tanımlıydı ama HİÇBİR YERDE kontrol
             * edilmiyordu (Düzeltme listesi md.18.3): kullanılmayan yetki,
             * kapalı sandığın açık kapısıdır. Kart PDF'i basın kartının
             * kendisi; kimin indirebileceği yetkiden okunmalı.
             */
            ->visible(fn () => $this->akreditasyon?->guncelKart?->pdf_yolu !== null
                && Auth::user()?->can('kart.indir'))
            ->action(fn (): StreamedResponse => $this->pdfAkisi());
    }

    private function pdfAkisi(): StreamedResponse
    {
        // Görünürlük yetmez: eylem adresi doğrudan da çağrılabilir.
        abort_unless(Auth::user()?->can('kart.indir'), 403);

        $kart = $this->akreditasyon->guncelKart;

        return Storage::disk($kart->disk)->download(
            $kart->pdf_yolu,
            'basin-karti-'.$this->akreditasyon->kart_no.'.pdf',
        );
    }

    /**
     * 🔑 Metin `App\Support\KartDurumu`'nda: aynı cümleleri üye panosundaki
     * `KartimOzeti` widget'ı da gösteriyor. Kopyalanmış olsaydı biri
     * düzeltilip diğeri unutulurdu.
     */
    public function durumMesaji(): ?string
    {
        return KartDurumu::mesaj($this->akreditasyon);
    }
}
