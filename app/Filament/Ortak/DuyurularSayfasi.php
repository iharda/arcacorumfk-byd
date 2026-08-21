<?php

namespace App\Filament\Ortak;

use App\Models\Duyuru;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/** Kulüp duyuruları — akredite kullanıcı görünümü. */
abstract class DuyurularSayfasi extends MedyaMerkeziSayfasi
{
    use WithPagination;

    protected string $view = 'filament.ortak.duyurular';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Duyurular';

    protected static ?string $title = 'Kulüp duyuruları';

    protected static ?int $navigationSort = 10;

    public function getDuyurularProperty(): LengthAwarePaginator
    {
        return Duyuru::query()->yayinda()->latest('yayin_at')->paginate(10);
    }
}
