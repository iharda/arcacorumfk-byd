<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\GecisSonucu;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\GecisKaydi;
use App\Models\Kurum;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Yetkili panosu -- Plan v1.0 md.8 "raporlar".
 * Yetkilinin güne başlarken bakacağı dört sayı; hepsi tıklanabilir.
 */
class OzetSayilar extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    // Maç günü geçiş sayısı akıyor; pano kendini tazelesin.
    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->can('basvuru.gor') ?? false;
    }

    protected function getStats(): array
    {
        $bekleyen = Basvuru::query()->kuyrukta()->count();
        $yeni = Basvuru::query()->kuyrukta()->where('durum', BasvuruDurumu::Gonderildi->value)->count();

        $bugunGecis = GecisKaydi::whereDate('okundu_at', today())->count();
        $bugunRet = GecisKaydi::whereDate('okundu_at', today())
            ->where('sonuc', '!=', GecisSonucu::Izinli->value)->count();

        return [
            Stat::make('Kuyrukta başvuru', (string) $bekleyen)
                ->description($yeni > 0 ? "{$yeni} tanesi hiç açılmadı" : 'Hepsi incelemeye alındı')
                ->descriptionIcon($yeni > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($yeni > 0 ? 'warning' : 'success')
                ->url(route('filament.yonetim.resources.basvurular.index')),

            Stat::make('Aktif akreditasyon', (string) Akreditasyon::where('durum', AkreditasyonDurumu::Aktif->value)->count())
                ->description(Akreditasyon::where('durum', AkreditasyonDurumu::Askida->value)->count().' askıda')
                ->descriptionIcon('heroicon-m-identification')
                ->url(route('filament.yonetim.resources.akreditasyonlar.index')),

            Stat::make('Akredite kurum', (string) Kurum::where('akreditasyon_durumu', 'akredite')->count())
                ->description(Kurum::where('akreditasyon_durumu', 'beklemede')->count().' beklemede')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->url(route('filament.yonetim.resources.kurumlar.index')),

            Stat::make('Bugünkü okutma', (string) $bugunGecis)
                ->description($bugunRet > 0 ? "{$bugunRet} tanesi reddedildi" : 'Reddedilen yok')
                ->descriptionIcon($bugunRet > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($bugunRet > 0 ? 'danger' : 'gray')
                ->url(route('filament.yonetim.resources.gecis-kayitlari.index')),
        ];
    }
}
