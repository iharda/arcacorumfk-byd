<?php

namespace App\Filament\Ortak;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruAkisi;
use App\Servisler\EvrakYukleyici;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * "Başvurum" sayfasının ortak gövdesi — kurum ve üye panelleri aynı ekranı
 * kullanır (Plan v1.0 md.5.5: onaya kadar yalnızca durum + evrak yükleme).
 *
 * Panel başına ayrı ince bir alt sınıf var; Filament sayfaları panelin kendi
 * dizininden keşfediyor.
 */
abstract class BasvurumSayfasi extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.ortak.basvurum';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Başvurum';

    protected static ?string $title = 'Başvurum';

    protected static ?int $navigationSort = 1;

    public ?Basvuru $basvuru = null;

    /** @var array<int, TemporaryUploadedFile|null> evrak_turu_id => dosya */
    public array $dosyalar = [];

    public function mount(): void
    {
        $this->basvuruyuYukle();

        abort_if($this->basvuru === null, 404);
    }

    private function basvuruyuYukle(): void
    {
        $this->basvuru = Basvuru::with(['evraklar.turu', 'kurum', 'akreditasyon'])
            ->where('kullanici_id', Auth::id())
            ->latest('id')
            ->first();
    }

    /** Menüde yalnızca başvurusu olanlara görünsün. */
    public static function shouldRegisterNavigation(): bool
    {
        return Basvuru::where('kullanici_id', Auth::id())->exists();
    }

    /** @return Collection<int, EvrakTuru> */
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

        // Servis dosyanın türünü finfo ile kendisi okur; sarmalayıcıya güvenilmez.
        try {
            app(EvrakYukleyici::class)->yukle($this->basvuru, $tur, $dosya);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        unset($this->dosyalar[$evrakTuruId]);
        $this->basvuru->refresh()->load('evraklar.turu');

        Notification::make()->title($tur->ad.' yüklendi.')->success()->send();
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
            ->body($this->basvuru->kurumTeyidiBekliyorMu()
                ? 'Önce kurumunuzun teyidi, ardından kulüp incelemesi bekleniyor.'
                : 'İnceleme tamamlandığında e-posta ile bilgilendirileceksiniz.')
            ->success()
            ->send();
    }
}
