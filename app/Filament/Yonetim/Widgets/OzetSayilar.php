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
        $yeni = Basvuru::query()->kuyrukta()
            ->whereIn('durum', BasvuruDurumu::degerleri(...BasvuruDurumu::acilmamis()))
            ->count();

        /*
         * 🪤 `whereDate()` PostgreSQL'de `CAST(okundu_at AS date) = ?` üretir;
         * sütuna fonksiyon uygulandığı için B-tree indeksi KULLANILAMAZ ve
         * seq scan olur (Düzeltme listesi md.17). Tabloda `okundu_at` ve
         * `(sonuc, okundu_at)` indeksleri var, aralık sorgusu ikisini de
         * kullanır. Maç günü ~30.000 satır hedefleniyor ve açık her pano
         * 60 saniyede bir 6 COUNT atıyor.
         */
        $bugun = today('Europe/Istanbul');
        $aralik = [$bugun->copy()->startOfDay(), $bugun->copy()->endOfDay()];

        $bugunGecis = GecisKaydi::whereBetween('okundu_at', $aralik)->count();
        // 🔑 UYARILAR RET DEĞİL: kişi geçti, yalnızca görevli uyarıldı (md.12).
        $bugunRet = GecisKaydi::whereBetween('okundu_at', $aralik)
            ->whereNotIn('sonuc', [
                GecisSonucu::Izinli->value,
                GecisSonucu::MukerrerOkutma->value,
                GecisSonucu::BaskaKapida->value,
            ])->count();

        /*
         * 🔤 Kart başlıkları ve alt satırları Cüneyt Bey revizyonunda
         * (03.09.2026) yeniden yazıldı: başlık NE SAYILDIĞINI, alt satır da
         * o sayının yanındaki bekleyen işi söylüyor.
         */
        return [
            Stat::make('İnceleme bekleyen başvurular', (string) $bekleyen)
                ->description($yeni > 0 ? "{$yeni} yeni başvuru" : 'Yeni başvuru yok')
                ->descriptionIcon($yeni > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($yeni > 0 ? 'warning' : 'success')
                ->url(route('filament.yonetim.resources.basvurular.index')),

            Stat::make('Geçerli akreditasyonlar', (string) Akreditasyon::where('durum', AkreditasyonDurumu::Aktif->value)->count())
                ->description('Askıya alınan: '.Akreditasyon::where('durum', AkreditasyonDurumu::Askida->value)->count())
                ->descriptionIcon('heroicon-m-identification')
                ->url(route('filament.yonetim.resources.akreditasyonlar.index')),

            Stat::make('Onaylı medya kuruluşları', (string) Kurum::where('akreditasyon_durumu', 'akredite')->count())
                ->description('Onay bekleyen: '.Kurum::where('akreditasyon_durumu', 'beklemede')->count())
                ->descriptionIcon('heroicon-m-building-office-2')
                ->url(route('filament.yonetim.resources.kurumlar.index')),

            Stat::make('Bugünkü geçişler', (string) $bugunGecis)
                ->description('Reddedilen geçiş: '.$bugunRet)
                ->descriptionIcon($bugunRet > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($bugunRet > 0 ? 'danger' : 'gray')
                ->url(route('filament.yonetim.resources.gecis-kayitlari.index')),
        ];
    }
}
