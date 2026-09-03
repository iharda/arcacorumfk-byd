<?php

namespace App\Filament\Yonetim\Resources\Bultenler;

use App\Filament\Yonetim\Resources\Bultenler\Pages\BultenDetay;
use App\Filament\Yonetim\Resources\Bultenler\Pages\ListBultenler;
use App\Filament\Yonetim\Resources\Bultenler\Tables\BultenlerTable;
use App\Models\Bulten;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Basın bültenleri -- Plan v1.0 md.8. */
class BultenResource extends Resource
{
    protected static ?string $model = Bulten::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Medya merkezi';

    protected static ?string $navigationLabel = 'Basın bültenleri';

    protected static ?string $modelLabel = 'bülten';

    protected static ?string $pluralModelLabel = 'bültenler';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordRouteKeyName = 'ulid';

    protected static ?string $slug = 'bultenler';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('icerik.yonet') ?? false;
    }

    public static function table(Table $table): Table
    {
        return BultenlerTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBultenler::route('/'),
            // S1: her kaydın kalıcı adresi olan detayı olacak.
            'detay' => BultenDetay::route('/{record}/detay'),
        ];
    }
}
