<?php

namespace App\Filament\Yonetim\Resources\Duyurular;

use App\Filament\Yonetim\Resources\Duyurular\Pages\ListDuyurular;
use App\Filament\Yonetim\Resources\Duyurular\Schemas\DuyuruFormu;
use App\Filament\Yonetim\Resources\Duyurular\Tables\DuyurularTable;
use App\Models\Duyuru;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Kulüp duyuruları -- Plan v1.0 md.8. */
class DuyuruResource extends Resource
{
    protected static ?string $model = Duyuru::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Medya merkezi';

    protected static ?string $navigationLabel = 'Duyurular';

    protected static ?string $modelLabel = 'duyuru';

    protected static ?string $pluralModelLabel = 'duyurular';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordRouteKeyName = 'ulid';

    protected static ?string $slug = 'duyurular';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('icerik.yonet') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(DuyuruFormu::alanlar());
    }

    public static function table(Table $table): Table
    {
        return DuyurularTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListDuyurular::route('/')];
    }
}
