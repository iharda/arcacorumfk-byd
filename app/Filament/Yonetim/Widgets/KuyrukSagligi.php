<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Support\TarihMetni;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Kuyruk sağlığı -- briefi md. B.3, Widget A.
 *
 * 🔑 Mevcut `OzetSayilar` "kaç tane var" diyor, "ne kadar bekledi" demiyor.
 * Kuyruk YAŞI hizmet kalitesinin tek göstergesi: on başvuru da olsa en
 * eskisi on gündür bekliyorsa sorun vardır.
 */
class KuyrukSagligi extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|array|null $columns = 3;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->can('basvuru.gor') ?? false;
    }

    protected function getStats(): array
    {
        return [
            $this->enEskiBekleyen(),
            $this->ortalamaKararSuresi(),
            $this->bugunKararaBaglanan(),
        ];
    }

    private function enEskiBekleyen(): Stat
    {
        $enEski = Basvuru::query()->kuyrukta()->min('gonderildi_at');

        if ($enEski === null) {
            return Stat::make('En uzun bekleyen başvuru', '—')
                ->description('Kuyruk boş')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        $gun = (int) Carbon::parse($enEski)->startOfDay()->diffInDays(now()->startOfDay());

        return Stat::make('En uzun bekleyen başvuru', $gun.' gün')
            ->description(TarihMetni::uzunEkli(Carbon::parse($enEski)).' alındı')
            ->descriptionIcon('heroicon-m-clock')
            ->color(match (true) {
                $gun >= 7 => 'danger',
                $gun >= 3 => 'warning',
                default => 'success',
            })
            ->url(route('filament.yonetim.resources.basvurular.index'));
    }

    private function ortalamaKararSuresi(): Stat
    {
        /*
         * 🪤 `whereDate()` PostgreSQL'de indeksi devre dışı bırakır
         * (Düzeltme listesi md.17); aralık sorgusu kullanılıyor.
         * Ortalama VERİTABANINDA hesaplanır: 30 günlük kararları PHP'ye
         * çekip toplamak gereksiz.
         */
        $ortalamaSaniye = Basvuru::query()
            ->whereNotNull('karar_at')
            ->whereNotNull('gonderildi_at')
            ->whereBetween('karar_at', [now()->copy()->subDays(30), now()])
            ->selectRaw('avg(extract(epoch from (karar_at - gonderildi_at))) as ort')
            ->first()?->ort;

        if ($ortalamaSaniye === null) {
            return Stat::make('Ortalama sonuçlanma süresi', '—')
                ->description('Son 30 günde sonuçlanan başvuru yok')
                ->descriptionIcon('heroicon-m-scale')
                ->color('gray');
        }

        $saat = (float) $ortalamaSaniye / 3600;

        // 🔤 Ondalık ayracı VİRGÜL: "2.7 gün" değil "2,7 gün" (Cüneyt Bey, 03.09.2026).
        return Stat::make(
            'Ortalama sonuçlanma süresi',
            $saat >= 48
                ? TarihMetni::sayi($saat / 24).' gün'
                : TarihMetni::sayi($saat).' saat',
        )
            ->description('Son 30 gün')
            ->descriptionIcon('heroicon-m-scale')
            ->color($saat > 168 ? 'warning' : 'gray');
    }

    private function bugunKararaBaglanan(): Stat
    {
        $bugun = today('Europe/Istanbul');
        $aralik = [$bugun->copy()->startOfDay(), $bugun->copy()->endOfDay()];

        $sayi = Basvuru::whereBetween('karar_at', $aralik)->count();
        $onay = Basvuru::whereBetween('karar_at', $aralik)
            ->where('durum', BasvuruDurumu::Onaylandi->value)
            ->count();

        return Stat::make('Bugün sonuçlanan başvurular', (string) $sayi)
            ->description($sayi > 0 ? "{$onay} onaylandı" : 'Bugün sonuçlanan başvuru yok')
            ->descriptionIcon('heroicon-m-check-badge')
            ->color($sayi > 0 ? 'success' : 'gray');
    }
}
