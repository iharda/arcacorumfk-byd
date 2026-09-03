<?php

namespace App\Filament\Yonetim\Resources\Antrenmanlar;

use App\Filament\Yonetim\Resources\Antrenmanlar\Pages\AntrenmanDetay;
use App\Filament\Yonetim\Resources\Antrenmanlar\Pages\ListAntrenmanlar;
use App\Filament\Yonetim\Resources\Antrenmanlar\Tables\AntrenmanlarTable;
use App\Models\Antrenman;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Basına açık antrenman takvimi -- Plan v1.0 md.8. */
class AntrenmanResource extends Resource
{
    protected static ?string $model = Antrenman::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Medya merkezi';

    protected static ?string $navigationLabel = 'Antrenman takvimi';

    protected static ?string $modelLabel = 'antrenman';

    protected static ?string $pluralModelLabel = 'antrenmanlar';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordRouteKeyName = 'ulid';

    protected static ?string $slug = 'antrenmanlar';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('icerik.yonet') ?? false;
    }

    public static function table(Table $table): Table
    {
        return AntrenmanlarTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAntrenmanlar::route('/'),
            // S1: her kaydın kalıcı adresi olan detayı olacak.
            'detay' => AntrenmanDetay::route('/{record}/detay'),
        ];
    }
}
