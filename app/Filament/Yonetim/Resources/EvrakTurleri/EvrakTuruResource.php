<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri;

use App\Filament\Yonetim\Resources\EvrakTurleri\Pages\EvrakTuruDuzenle;
use App\Filament\Yonetim\Resources\EvrakTurleri\Pages\EvrakTuruOlustur;
use App\Filament\Yonetim\Resources\EvrakTurleri\Pages\ListEvrakTurleri;
use App\Filament\Yonetim\Resources\EvrakTurleri\Tables\EvrakTurleriTable;
use App\Models\EvrakTuru;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Evrak türleri -- Tutarsızlık incelemesi M7.3.
 *
 * 💀 `EvrakTuruSeeder`'ın kendi yorumu "Bunlar VERI; yetkili panelden ekleyip
 * cikarabilir" diyordu ama böyle bir ekran YOKTU. Yani her yeni belge talebi
 * bir geliştirici + bir dağıtım demekti. İmza sirküleri bunun ikinci örneği;
 * üçüncüsü gelmeden ekran yapıldı. (M3 №8)
 *
 * 🔒 Yetki `ayar.yonet` (yalnızca super). Bu ekran KAMUYA AÇIK başvuru
 * formunun alanlarını belirliyor: bir belgeyi zorunlu yapmak, boyut sınırını
 * ya da izinli formatları değiştirmek doğrudan başvuru kabulünü etkiler.
 * Bu yüzden sıradan yetkiliye değil sistem ayarlarıyla aynı kapıya bağlı.
 */
class EvrakTuruResource extends Resource
{
    protected static ?string $model = EvrakTuru::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Evrak türleri';

    protected static ?string $modelLabel = 'evrak türü';

    protected static ?string $pluralModelLabel = 'evrak türleri';

    // Ayarların hemen yanı: ikisi de yapılandırma.
    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'evrak-turleri';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ayar.yonet') ?? false;
    }

    /**
     * 🔑 SİLME YOK. Mevcut evraklar kaydın ADINA bakıyor
     * (`Evrak::ekranBasligi()`); tür silinirse geçmiş başvurulardaki belgeler
     * "Evrak" diye isimsiz kalır ve karar geçmişi okunamaz hâle gelir.
     * Kullanımdan kaldırmanın yolu `aktif = false`.
     */
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return EvrakTurleriTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvrakTurleri::route('/'),
            'olustur' => EvrakTuruOlustur::route('/olustur'),
            'duzenle' => EvrakTuruDuzenle::route('/{record}/duzenle'),
        ];
    }
}
