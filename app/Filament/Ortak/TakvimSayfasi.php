<?php

namespace App\Filament\Ortak;

use App\Models\Antrenman;
use BackedEnum;
use Illuminate\Support\Collection;

/** Basına açık antrenman takvimi — akredite kullanıcı görünümü. */
abstract class TakvimSayfasi extends MedyaMerkeziSayfasi
{
    protected string $view = 'filament.ortak.takvim';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Antrenman takvimi';

    protected static ?string $title = 'Antrenman takvimi';

    protected static ?int $navigationSort = 11;

    /** @return Collection<int, Antrenman> */
    public function getYaklasanlarProperty(): Collection
    {
        return Antrenman::query()->yayinda()
            ->where('baslangic_at', '>=', now()->startOfDay())
            ->orderBy('baslangic_at')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, Antrenman> */
    public function getGecmisProperty(): Collection
    {
        return Antrenman::query()->yayinda()
            ->where('baslangic_at', '<', now()->startOfDay())
            ->orderByDesc('baslangic_at')
            ->limit(10)
            ->get();
    }
}
