<?php

namespace App\Filament\Yonetim\Resources\Basvurus;

use App\Enums\BasvuruDurumu;
use App\Filament\Yonetim\Resources\Basvurus\Pages\Inceleme;
use App\Filament\Yonetim\Resources\Basvurus\Pages\ListBasvurus;
use App\Filament\Yonetim\Resources\Basvurus\Tables\BasvurusTable;
use App\Models\Basvuru;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Başvuru kuyruğu -- Plan v1.0 md.8 (Yetkili paneli).
 * Kayıt oluşturma/düzenleme YOK: başvuru kamuya açık formdan doğar, değişiklik
 * yalnızca inceleme ekranındaki kararlarla olur (BasvuruAkisi).
 */
class BasvuruResource extends Resource
{
    protected static ?string $model = Basvuru::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Başvurular';

    protected static ?string $modelLabel = 'başvuru';

    protected static ?string $pluralModelLabel = 'başvurular';

    protected static ?int $navigationSort = 1;

    /*
     * Adreslerde sıralı id GÖRÜNMEZ (Plan v1.0 md.11 — tahmin edilemez kimlik).
     * Bunu yazmazsan Filament getUrl() sayısal id üretir, sayfanın ulid araması
     * tutmaz ve inceleme ekranı 404 döner.
     */
    protected static ?string $recordRouteKeyName = 'ulid';

    // Slug ELLE: türetilen ad "basvurus" oluyordu.
    protected static ?string $slug = 'basvurular';

    public static function table(Table $table): Table
    {
        return BasvurusTable::configure($table);
    }

    /** Kuyrukta bekleyen başvuru sayısı menüde rozet olarak görünür. */
    public static function getNavigationBadge(): ?string
    {
        $adet = Basvuru::query()->kuyrukta()->count();

        return $adet > 0 ? (string) $adet : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Basvuru::query()->where('durum', BasvuruDurumu::Gonderildi->value)->exists()
            ? 'warning'
            : 'gray';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBasvurus::route('/'),
            'inceleme' => Inceleme::route('/{record}/inceleme'),
        ];
    }
}
