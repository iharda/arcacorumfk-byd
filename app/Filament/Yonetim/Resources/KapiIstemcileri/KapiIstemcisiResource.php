<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri;

use App\Filament\Yonetim\Resources\KapiIstemcileri\Pages\KapiIstemcisiDetay;
use App\Filament\Yonetim\Resources\KapiIstemcileri\Pages\ListKapiIstemcileri;
use App\Filament\Yonetim\Resources\KapiIstemcileri\Tables\KapiIstemcileriTable;
use App\Models\KapiIstemcisi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Turnike / gişe istemcileri -- Plan v1.0 md.7. */
class KapiIstemcisiResource extends Resource
{
    protected static ?string $model = KapiIstemcisi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Kapılar';

    protected static ?string $modelLabel = 'kapı istemcisi';

    protected static ?string $pluralModelLabel = 'kapılar';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordRouteKeyName = 'ulid';

    protected static ?string $slug = 'kapilar';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('kapi.yonet') ?? false;
    }

    public static function table(Table $table): Table
    {
        return KapiIstemcileriTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKapiIstemcileri::route('/'),
            // S1: her kaydın kalıcı adresi olan detayı olacak.
            'detay' => KapiIstemcisiDetay::route('/{record}/detay'),
        ];
    }
}
