<?php

namespace App\Filament\Kurum\Pages;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruAkisi;
use App\Servisler\EvrakYukleyici;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Kurum paneli — "Başvurum".
 * Plan v1.0 md.5.1 / md.5.5: onaya kadar kurum yalnızca başvuru durumunu görür
 * ve evrak yükler. Eksik evrak talebi geldiyse İŞARETLİ alanlar burada listelenir.
 */
class Basvurum extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.kurum.pages.basvurum';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Başvurum';

    protected static ?string $title = 'Başvurum';

    protected static ?int $navigationSort = 1;

    public ?Basvuru $basvuru = null;

    /** @var array<int, TemporaryUploadedFile|null> evrak_turu_id => dosya */
    public array $dosyalar = [];

    public function mount(): void
    {
        $this->basvuru = Basvuru::with(['evraklar.turu', 'kurum'])
            ->where('kullanici_id', Auth::id())
            ->latest('id')
            ->first();

        abort_if($this->basvuru === null, 404);
    }

    /** @return \Illuminate\Support\Collection<int, EvrakTuru> */
    public function getEvrakTurleriProperty()
    {
        return EvrakTuru::turIcin($this->basvuru->tur);
    }

    public function getYuklenebilirMiProperty(): bool
    {
        return in_array($this->basvuru->durum, [
            BasvuruDurumu::Taslak,
            BasvuruDurumu::EksikEvrak,
        ], true);
    }

    public function yukle(int $evrakTuruId): void
    {
        abort_unless($this->yuklenebilirMi, 403);

        $dosya = $this->dosyalar[$evrakTuruId] ?? null;

        if (! $dosya instanceof TemporaryUploadedFile) {
            Notification::make()->title('Önce bir dosya seçin.')->danger()->send();

            return;
        }

        $tur = EvrakTuru::findOrFail($evrakTuruId);

        // TemporaryUploadedFile zaten UploadedFile'dan türüyor; servis dosyanın
        // türünü kendisi finfo ile okuyor, sarmalayıcıya güvenmiyoruz.
        try {
            app(EvrakYukleyici::class)->yukle($this->basvuru, $tur, $dosya);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        unset($this->dosyalar[$evrakTuruId]);
        $this->basvuru->refresh()->load('evraklar.turu');

        Notification::make()->title($tur->ad . ' yüklendi.')->success()->send();
    }

    public function gonder(): void
    {
        abort_unless($this->yuklenebilirMi, 403);

        try {
            app(BasvuruAkisi::class)->gonder($this->basvuru);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->basvuru->refresh();

        Notification::make()
            ->title('Başvurunuz gönderildi.')
            ->body('İnceleme tamamlandığında e-posta ile bilgilendirileceksiniz.')
            ->success()
            ->send();
    }
}
