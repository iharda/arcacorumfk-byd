<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\BasvuruDurumu;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Son 14 günün karar dağılımı -- briefi md. B.3, Widget B.
 *
 * 🚫 Yeni npm paketi YOK: Filament'in kendi ChartWidget'ı (Chart.js zaten
 * panelde yüklü).
 * 🪤 Gün grupları VERİTABANINDA (`date_trunc`) çıkarılır; 14 günün tüm
 * satırlarını PHP'ye çekip gruplamak gereksiz.
 */
class KararDagilimi extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Karar dağılımı (son 14 gün)';

    protected ?string $maxHeight = '260px';

    /** Kararlar gün içinde birikir; dakikalık tazelemeye gerek yok. */
    protected ?string $pollingInterval = null;

    private const GUN = 14;

    public static function canView(): bool
    {
        return auth()->user()?->can('basvuru.gor') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $bugun = today('Europe/Istanbul');
        $baslangic = $bugun->copy()->subDays(self::GUN - 1)->startOfDay();

        /*
         * 🔑 Eloquent DEĞİL ham sorgu: toplulaştırılmış satırlar model değil,
         * `durum` sütunu enum'a cast edilmesin -- burada yalnızca gruplama
         * anahtarı olarak kullanılıyor. Silinen başvurular sayılmasın diye
         * `deleted_at` koşulu ELLE eklendi (SoftDeletes kapsamı burada yok).
         */
        $satirlar = DB::table('basvurular')
            ->whereNull('deleted_at')
            ->whereNotNull('karar_at')
            // 🪤 whereDate() DEĞİL: sütuna fonksiyon uygulanırsa indeks ölür.
            ->whereBetween('karar_at', [$baslangic, $bugun->copy()->endOfDay()])
            ->selectRaw("date_trunc('day', karar_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Istanbul') as gun, durum, count(*) as adet")
            ->groupBy('gun', 'durum')
            ->get();

        $etiketler = [];
        $anahtarlar = [];

        for ($i = 0; $i < self::GUN; $i++) {
            $gun = $baslangic->copy()->addDays($i);
            $anahtarlar[] = $gun->format('Y-m-d');
            $etiketler[] = $gun->format('d.m');
        }

        $seriler = [
            BasvuruDurumu::Onaylandi->value => ['etiket' => 'Onaylandı', 'renk' => '#16a34a'],
            BasvuruDurumu::Reddedildi->value => ['etiket' => 'Reddedildi', 'renk' => '#dc2626'],
            BasvuruDurumu::EksikEvrak->value => ['etiket' => 'Belge istendi', 'renk' => '#d97706'],
        ];

        $sayilar = [];

        foreach ($satirlar as $satir) {
            $gun = Carbon::parse($satir->gun)->format('Y-m-d');
            $sayilar[(string) $satir->durum][$gun] = (int) $satir->adet;
        }

        return [
            'datasets' => collect($seriler)->map(fn (array $seri, string $durum) => [
                'label' => $seri['etiket'],
                'data' => array_map(fn (string $gun) => $sayilar[$durum][$gun] ?? 0, $anahtarlar),
                'backgroundColor' => $seri['renk'],
                'borderColor' => $seri['renk'],
            ])->values()->all(),
            'labels' => $etiketler,
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Yığılmış çubuk: günün toplam karar sayısı tek bakışta okunsun.
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['display' => true]],
        ];
    }
}
