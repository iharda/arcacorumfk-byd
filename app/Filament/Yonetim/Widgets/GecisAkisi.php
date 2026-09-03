<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\GecisSonucu;
use App\Models\GecisKaydi;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bugünün saatlik geçiş akışı -- briefi md. B.3, Widget C.
 *
 * 🔑 60 sn polling YALNIZCA burada: maç günü akan tek veri bu. Kurum ve üye
 * panosunda polling hiç yok.
 * 🚫 Geçiş yoksa widget GİZLENİR -- maç günü dışında boş grafik yer kaplamasın.
 */
class GecisAkisi extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Bugünkü geçiş akışı';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        if (! (auth()->user()?->can('gecis.gor') ?? false)) {
            return false;
        }

        return GecisKaydi::whereBetween('okundu_at', self::bugun())->exists();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function bugun(): array
    {
        $gun = today('Europe/Istanbul');

        return [$gun->copy()->startOfDay(), $gun->copy()->endOfDay()];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        /*
         * 🪤 whereDate() DEĞİL: `gecis_kayitlari` maç günü ~30.000 satıra
         * çıkıyor ve `okundu_at` indeksli. Aralık sorgusu indeksi kullanır.
         */
        // 🔑 Ham sorgu: toplulaştırılmış satır model değil, `sonuc` enum'a
        // cast edilmesin -- aşağıda tek yerde `from()` ile çevriliyor.
        $satirlar = DB::table('gecis_kayitlari')
            ->whereBetween('okundu_at', self::bugun())
            ->selectRaw("date_part('hour', okundu_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Istanbul') as saat, sonuc, count(*) as adet")
            ->groupBy('saat', 'sonuc')
            ->get();

        $izinli = array_fill(0, 24, 0);
        $uyarili = array_fill(0, 24, 0);

        foreach ($satirlar as $satir) {
            $saat = (int) $satir->saat;
            $sonuc = GecisSonucu::from((string) $satir->sonuc);

            /*
             * 🔑 UYARI RET DEĞİL (Düzeltme listesi md.12): mükerrer okutma ve
             * başka kapıda okutma kişiyi içeri alır, yalnızca görevliyi uyarır.
             * Grafikte de ret sayılmamalı.
             */
            if ($sonuc === GecisSonucu::Izinli) {
                $izinli[$saat] += (int) $satir->adet;
            } elseif ($sonuc->uyariMi()) {
                $uyarili[$saat] += (int) $satir->adet;
            }
        }

        // Yalnızca hareket olan saat aralığı: gece 00-06 boş sütunla dolmasın.
        $dolu = array_keys(array_filter(
            range(0, 23),
            fn (int $saat) => $izinli[$saat] > 0 || $uyarili[$saat] > 0,
        ));

        $ilk = $dolu === [] ? 0 : min($dolu);
        $son = $dolu === [] ? 23 : max($dolu);
        $aralik = range($ilk, $son);

        return [
            'datasets' => [
                [
                    'label' => 'İzinli',
                    'data' => array_map(fn (int $s) => $izinli[$s], $aralik),
                    'backgroundColor' => '#16a34a',
                    'borderColor' => '#16a34a',
                ],
                [
                    'label' => 'Uyarılı',
                    'data' => array_map(fn (int $s) => $uyarili[$s], $aralik),
                    'backgroundColor' => '#d97706',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => array_map(fn (int $s) => str_pad((string) $s, 2, '0', STR_PAD_LEFT).':00', $aralik),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
