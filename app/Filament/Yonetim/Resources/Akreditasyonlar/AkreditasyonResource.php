<?php

namespace App\Filament\Yonetim\Resources\Akreditasyonlar;

use App\Enums\AkreditasyonDurumu;
use App\Filament\Yonetim\Resources\Akreditasyonlar\Pages\ListAkreditasyonlar;
use App\Filament\Yonetim\Resources\Akreditasyonlar\Tables\AkreditasyonlarTable;
use App\Models\Akreditasyon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Akreditasyonlar -- Plan v1.0 md.8: iptal / askı / yeniden aktifleştirme.
 * Kayıt buradan OLUŞTURULMAZ; onaylanan başvurudan doğar.
 */
class AkreditasyonResource extends Resource
{
    protected static ?string $model = Akreditasyon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Akreditasyonlar';

    protected static ?string $modelLabel = 'akreditasyon';

    protected static ?string $pluralModelLabel = 'akreditasyonlar';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordRouteKeyName = 'ulid';

    protected static ?string $slug = 'akreditasyonlar';

    public static function table(Table $table): Table
    {
        return AkreditasyonlarTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $adet = Akreditasyon::query()->where('durum', AkreditasyonDurumu::Aktif->value)->count();

        return $adet > 0 ? (string) $adet : null;
    }

    public static function getPages(): array
    {
        return ['index' => ListAkreditasyonlar::route('/')];
    }
}
