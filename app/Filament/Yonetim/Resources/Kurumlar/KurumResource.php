<?php

namespace App\Filament\Yonetim\Resources\Kurumlar;

use App\Filament\Yonetim\Resources\Kurumlar\Pages\KurumDetay;
use App\Filament\Yonetim\Resources\Kurumlar\Pages\KurumDuzenle;
use App\Filament\Yonetim\Resources\Kurumlar\Pages\ListKurumlar;
use App\Filament\Yonetim\Resources\Kurumlar\Tables\KurumlarTable;
use App\Models\Kurum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Medya kuruluşları -- Plan v1.0 md.8.
 * Kayıt buradan OLUŞTURULMAZ; başvuru formundan doğar. Yetkili durumu görür ve
 * gerekirse akreditasyonu kaldırır.
 */
class KurumResource extends Resource
{
    protected static ?string $model = Kurum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Kurumlar';

    protected static ?string $modelLabel = 'kurum';

    protected static ?string $pluralModelLabel = 'kurumlar';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordRouteKeyName = 'ulid';

    // Slug ELLE: dizin adı + model çoğulundan "kurumlar/kurums" üretiliyordu.
    protected static ?string $slug = 'kurumlar';

    public static function table(Table $table): Table
    {
        return KurumlarTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKurumlar::route('/'),
            // S1: her kaydın kalıcı adresi olan detayı olacak (T5).
            'detay' => KurumDetay::route('/{record}/detay'),
            'duzenle' => KurumDuzenle::route('/{record}/duzenle'),
        ];
    }
}
