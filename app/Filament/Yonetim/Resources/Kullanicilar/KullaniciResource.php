<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar;

use App\Filament\Yonetim\Resources\Kullanicilar\Pages\KullaniciDetay;
use App\Filament\Yonetim\Resources\Kullanicilar\Pages\KullaniciDuzenle;
use App\Filament\Yonetim\Resources\Kullanicilar\Pages\ListKullanicilar;
use App\Filament\Yonetim\Resources\Kullanicilar\Tables\KullanicilarTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Kullanıcı ve rol yönetimi -- Düzeltme listesi md.6.
 *
 * 💀 Bu ekran YOKTU. `kullanici.yonet` yetkisi tanımlıydı ama hiçbir yerde
 * kontrol edilmiyordu; yeni yetkili eklemek, ayrılanı kapatmak, rol
 * değiştirmek ve 2FA sıfırlamak yalnızca tinker/SQL ile yapılabiliyordu.
 * Devirden sonra müşteri hiçbirini yapamazdı.
 *
 * 🔒 Erişim `UserPolicy` üzerinden; `canAccess` YOKLUĞU kontrol yokluğu
 * demek değil ama burada ikisi de var: menüde görünmemesi yetmez.
 */
class KullaniciResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Kullanıcılar';

    protected static ?string $modelLabel = 'kullanıcı';

    protected static ?string $pluralModelLabel = 'kullanıcılar';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordRouteKeyName = 'ulid';

    // Slug ELLE: dizin adından "users" üretiliyordu.
    protected static ?string $slug = 'kullanicilar';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', User::class) ?? false;
    }

    public static function table(Table $table): Table
    {
        return KullanicilarTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKullanicilar::route('/'),
            'detay' => KullaniciDetay::route('/{record}/detay'),
            'duzenle' => KullaniciDuzenle::route('/{record}/duzenle'),
        ];
    }
}
