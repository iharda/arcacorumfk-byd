<?php

namespace App\Filament\Kurum\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Davet;
use App\Models\Kurum;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Kurum panosu özeti -- briefi md. B.2, Widget 1.
 * "Kaç kartım var, kontenjanım doldu mu, benden bekleyen var mı?"
 *
 * 🔒 Her sayı `kurum_id` ile kendi kurumuna daraltılır.
 * 🚫 Polling YOK: kurum yetkilisi seyrek giriyor, sayılar dakikalık değişmiyor.
 */
class KurumOzeti extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return Auth::user()?->kurum !== null;
    }

    protected function getStats(): array
    {
        $kurum = Auth::user()?->kurum;

        if (! $kurum instanceof Kurum) {
            return [];
        }

        return [
            $this->aktifKart($kurum),
            $this->teyitBekleyen($kurum),
            $this->acikDavet($kurum),
            $this->calisan($kurum),
        ];
    }

    private function aktifKart(Kurum $kurum): Stat
    {
        $aktif = Akreditasyon::where('kurum_id', $kurum->getKey())
            ->where('durum', AkreditasyonDurumu::Aktif->value)
            ->count();

        if ($kurum->kontenjan === null) {
            return Stat::make('Aktif kart', (string) $aktif)
                ->description('Kontenjan sınırsız')
                ->descriptionIcon('heroicon-m-identification')
                ->url(route('filament.kurum.pages.calisanlar'));
        }

        $oran = $kurum->kontenjan > 0 ? (int) round($aktif / $kurum->kontenjan * 100) : 100;
        $renk = match (true) {
            $oran >= 100 => 'danger',
            $oran >= 90 => 'warning',
            default => 'gray',
        };

        return Stat::make('Aktif kart', $aktif.' / '.$kurum->kontenjan)
            // Doluluk çubuğu satır içi stille: panelde kendi Tailwind
            // sınıflarımız derlenmiyor, yeni sınıf adı sessizce çalışmaz.
            ->description(new HtmlString(
                '<span>%'.$oran.' dolu</span>'
                .'<span style="display:block; margin-top:.35rem; height:.35rem; border-radius:999px;'
                .' background:rgba(127,127,127,.2); overflow:hidden;">'
                .'<span style="display:block; height:100%; width:'.min($oran, 100).'%;'
                .' background:'.($oran >= 100 ? '#dc2626' : ($oran >= 90 ? '#d97706' : '#16a34a')).';"></span>'
                .'</span>'
            ))
            ->color($renk)
            ->url(route('filament.kurum.pages.calisanlar'));
    }

    private function teyitBekleyen(Kurum $kurum): Stat
    {
        $bekleyenler = Basvuru::where('kurum_id', $kurum->getKey())->teyitBekleyen();
        $sayi = (clone $bekleyenler)->count();
        $enEski = (clone $bekleyenler)->min('gonderildi_at');

        $gun = $enEski === null
            ? null
            : (int) now()->startOfDay()->diffInDays(Carbon::parse($enEski)->startOfDay());

        return Stat::make('Teyit bekleyen', (string) $sayi)
            ->description($sayi > 0 && $gun !== null
                ? ($gun > 0 ? "En eskisi {$gun} gündür bekliyor" : 'Bugün geldi')
                : 'Bekleyen yok')
            ->descriptionIcon($sayi > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
            ->color($sayi > 0 ? 'warning' : 'success')
            ->url(route('filament.kurum.pages.calisanlar'));
    }

    private function acikDavet(Kurum $kurum): Stat
    {
        $acik = Davet::where('kurum_id', $kurum->getKey())
            ->whereNull('kullanildi_at')
            ->whereNull('iptal_at')
            ->where('gecerlilik_bitis', '>', now());

        $sayi = (clone $acik)->count();
        // 🪤 whereDate() PostgreSQL'de indeksi devre dışı bırakır; aralık sorgusu.
        $buHafta = (clone $acik)
            ->whereBetween('gecerlilik_bitis', [now(), now()->copy()->addWeek()])
            ->count();

        return Stat::make('Açık davet', (string) $sayi)
            ->description($buHafta > 0 ? "{$buHafta} tanesi bu hafta doluyor" : 'Yakında dolan yok')
            ->descriptionIcon('heroicon-m-envelope-open')
            ->color('gray')
            ->url(route('filament.kurum.pages.calisanlar'));
    }

    private function calisan(Kurum $kurum): Stat
    {
        $aktif = User::where('kurum_id', $kurum->getKey())->whereNull('ayrildi_at')->count();
        $ayrilan = User::where('kurum_id', $kurum->getKey())->whereNotNull('ayrildi_at')->count();

        return Stat::make('Çalışan', (string) $aktif)
            ->description($ayrilan > 0 ? "{$ayrilan} ayrılan" : 'Ayrılan yok')
            ->descriptionIcon('heroicon-m-users')
            ->color('gray')
            ->url(route('filament.kurum.pages.calisanlar'));
    }
}
