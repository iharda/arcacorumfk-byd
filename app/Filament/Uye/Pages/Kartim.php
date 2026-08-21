<?php

namespace App\Filament\Uye\Pages;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
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

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-identification';

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
            ->visible(fn () => $this->akreditasyon?->guncelKart?->pdf_yolu !== null)
            ->action(fn (): StreamedResponse => $this->pdfAkisi());
    }

    private function pdfAkisi(): StreamedResponse
    {
        $kart = $this->akreditasyon->guncelKart;

        return Storage::disk($kart->disk)->download(
            $kart->pdf_yolu,
            'basin-karti-' . $this->akreditasyon->kart_no . '.pdf',
        );
    }

    public function durumMesaji(): ?string
    {
        $a = $this->akreditasyon;

        if (! $a) {
            return 'Henüz akreditasyonunuz yok. Başvurunuz onaylandığında kartınız burada görünür.';
        }

        if ($a->durum === AkreditasyonDurumu::Iptal) {
            return 'Akreditasyonunuz iptal edilmiştir; kart kulüp girişlerinde geçerli değildir.';
        }

        if ($a->durum === AkreditasyonDurumu::Askida) {
            return 'Akreditasyonunuz askıdadır; askı kaldırılana kadar kart geçerli değildir.';
        }

        if (! $a->guncelKart) {
            return 'Kartınız hazırlanıyor. Birkaç dakika içinde burada görünecek.';
        }

        return null;
    }
}
