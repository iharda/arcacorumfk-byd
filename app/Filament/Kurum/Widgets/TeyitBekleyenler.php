<?php

namespace App\Filament\Kurum\Widgets;

use App\Filament\Kurum\Ortak\TeyitEylemleri;
use App\Models\Basvuru;
use App\Models\Kurum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Teyit bekleyen başvurular -- briefi md. B.2, Widget 2.
 *
 * 💀 Bu işin var olduğu bugün yalnızca `Çalışanlar` sayfasının içinde
 * görünüyor. Kurum yetkilisi haftada bir giriyor; panoda görmediği işi
 * yapmıyor ve başvuru kuyrukta bekliyor. Widget'ın tek amacı işi öne çıkarmak.
 *
 * 🔑 Eylemler `TeyitEylemleri` trait'inden -- `Calisanlar` sayfasındakiyle
 * AYNI kod, aynı servis. Kopyalanmadı.
 */
class TeyitBekleyenler extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use TeyitEylemleri;

    protected string $view = 'filament.kurum.widgets.teyit-bekleyenler';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** En fazla bu kadar satır gösterilir; gerisi Çalışanlar sayfasında. */
    private const LIMIT = 5;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::user()?->kurum !== null && static::bekleyenler()->isNotEmpty();
    }

    public function kurum(): ?Kurum
    {
        return Auth::user()?->kurum;
    }

    /** @return Collection<int, Basvuru> */
    public static function bekleyenler(): Collection
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kurum = Auth::user()?->kurum;

        return self::$onbellek = $kurum === null
            ? collect()
            : Basvuru::with('kullanici')
                ->where('kurum_id', $kurum->getKey())      // 🔒 kapsam
                ->teyitBekleyen()
                ->orderBy('gonderildi_at')
                ->limit(self::LIMIT)
                ->get();
    }

    /** @return Collection<int, Basvuru> */
    public function getBekleyenlerProperty(): Collection
    {
        return static::bekleyenler();
    }

    /** Listede gösterilmeyen kalan iş sayısı -- sessizce kırpmayalım. */
    public function getKalanProperty(): int
    {
        $kurum = $this->kurum();

        if ($kurum === null) {
            return 0;
        }

        return max(0, Basvuru::where('kurum_id', $kurum->getKey())
            ->teyitBekleyen()
            ->count() - self::LIMIT);
    }
}
