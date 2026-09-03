<?php

namespace App\Filament\Yonetim\Resources\DenetimKayitlari;

use App\Filament\Yonetim\Resources\DenetimKayitlari\Pages\DenetimKaydiDetay;
use App\Filament\Yonetim\Resources\DenetimKayitlari\Pages\ListDenetimKayitlari;
use App\Filament\Yonetim\Resources\DenetimKayitlari\Tables\DenetimKayitlariTable;
use App\Models\DenetimKaydi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Denetim kaydı -- Plan v1.0 md.10.
 * Yalnızca OKUNUR. Kayıt veritabanı tetikleyicisiyle de kilitli: buradan
 * silme/düzenleme eylemi olmadığı gibi, doğrudan SQL de değiştiremez.
 */
class DenetimKaydiResource extends Resource
{
    protected static ?string $model = DenetimKaydi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Denetim kaydı';

    protected static ?string $modelLabel = 'denetim kaydı';

    protected static ?string $pluralModelLabel = 'denetim kaydı';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'denetim-kaydi';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('denetim.gor') ?? false;
    }

    public static function table(Table $table): Table
    {
        return DenetimKayitlariTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDenetimKayitlari::route('/'),
            // S1: her kaydın kalıcı adresi olan detayı olacak.
            'detay' => DenetimKaydiDetay::route('/{record}/detay'),
        ];
    }
}
