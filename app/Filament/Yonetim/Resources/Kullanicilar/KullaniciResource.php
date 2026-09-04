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

    /**
     * 🔑 SAYFA erişimi ile MENÜ ayrıldı (Cüneyt Bey revizyonu 05.09.2026).
     *
     * `canAccess` kaynağın BÜTÜN sayfalarını kapatıyor; yalnızca politikayı
     * gevşetmek yetmiyordu. Kulüp yetkilisi kurum detayından çalışana
     * tıklayabilsin diye kaynak ona da açık -- ama:
     *   · menü `shouldRegisterNavigation()` ile super'de kalır,
     *   · liste sayfası kendi içinde ayrıca yetki sorar (ListKullanicilar),
     *   · düzenleme/rol/2FA `UserPolicy` ile `kullanici.yonet`'te kalır.
     * Yani yetkiliye açılan tek şey KÜNYEYİ OKUMAK.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', User::class)
            || (auth()->user()?->hasRole(User::ROL_YETKILI) ?? false);
    }

    /** Menü yalnızca kullanıcı YÖNETEBİLENE görünür. */
    public static function shouldRegisterNavigation(): bool
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
