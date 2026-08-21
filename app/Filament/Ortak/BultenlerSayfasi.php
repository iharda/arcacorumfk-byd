<?php

namespace App\Filament\Ortak;

use App\Models\Bulten;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/** Basın bültenleri — akredite kullanıcı görünümü. */
abstract class BultenlerSayfasi extends MedyaMerkeziSayfasi
{
    use WithPagination;

    protected string $view = 'filament.ortak.bultenler';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Basın bültenleri';

    protected static ?string $title = 'Basın bültenleri';

    protected static ?int $navigationSort = 12;

    public function getBultenlerProperty(): LengthAwarePaginator
    {
        return Bulten::query()->yayinda()->latest('yayin_at')->paginate(10);
    }
}
