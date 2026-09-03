<?php

namespace App\Filament\Ortak;

use App\Models\Bulten;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Basın bültenleri — akredite kullanıcı görünümü.
 *
 * Duyurularla AYNI satır ve detay bileşenlerini kullanır; tek fark ekler.
 * Bültende kapak görseli yok, satırın kapak yuvası boş bırakılır.
 */
abstract class BultenlerSayfasi extends MedyaMerkeziSayfasi
{
    use WithPagination;

    protected string $view = 'filament.ortak.bultenler';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Basın bültenleri';

    protected static ?string $title = 'Basın bültenleri';

    protected static ?int $navigationSort = 12;

    #[Url(as: 'ara', keep: false)]
    public string $arama = '';

    #[Url(as: 'acik')]
    public ?string $acikUlid = null;

    protected static function gorulmeAlani(): ?string
    {
        return 'bulten_gorulme_at';
    }

    public function updatedArama(): void
    {
        $this->resetPage();
    }

    public function aramayiTemizle(): void
    {
        $this->arama = '';
        $this->resetPage();
    }

    public function getBultenlerProperty(): LengthAwarePaginator
    {
        return Bulten::query()
            // 🔑 `icerik` listede okunmaz; ekler ise satırdaki sayı rozeti için
            // gerekiyor (JSON kolon, ayrı sorgu doğurmaz).
            ->select(['id', 'ulid', 'baslik', 'ekler', 'yayin_at'])
            ->yayinda()
            ->when(filled($this->arama), fn (Builder $sorgu) => $sorgu
                ->where('baslik', 'ilike', '%'.$this->arama.'%'))
            ->latest('yayin_at')
            ->paginate(12);
    }

    public function getAcikBultenProperty(): ?Bulten
    {
        if (blank($this->acikUlid)) {
            return null;
        }

        return Bulten::query()->yayinda()->where('ulid', $this->acikUlid)->firstOrFail();
    }

    public function yeniMi(Bulten $bulten): bool
    {
        return $this->esik !== null
            && $bulten->yayin_at !== null
            && $bulten->yayin_at->gt($this->esik);
    }
}
