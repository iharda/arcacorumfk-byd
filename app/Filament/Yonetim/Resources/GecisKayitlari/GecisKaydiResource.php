<?php

namespace App\Filament\Yonetim\Resources\GecisKayitlari;

use App\Filament\Yonetim\Resources\GecisKayitlari\Pages\ListGecisKayitlari;
use App\Filament\Yonetim\Resources\GecisKayitlari\Tables\GecisKayitlariTable;
use App\Models\GecisKaydi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Geçiş kayıtları -- Plan v1.0 md.10.
 * Yalnızca OKUNUR: kayıt eklenir, değiştirilmez, silinmez.
 */
class GecisKaydiResource extends Resource
{
    protected static ?string $model = GecisKaydi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Geçiş kayıtları';

    protected static ?string $modelLabel = 'geçiş kaydı';

    protected static ?string $pluralModelLabel = 'geçiş kayıtları';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'gecis-kayitlari';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gecis.gor') ?? false;
    }

    public static function table(Table $table): Table
    {
        return GecisKayitlariTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListGecisKayitlari::route('/')];
    }
}
