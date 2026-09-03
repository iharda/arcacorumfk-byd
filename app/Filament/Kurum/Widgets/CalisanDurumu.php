<?php

namespace App\Filament\Kurum\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Models\Kurum;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Çalışan kart durumları -- briefi md. B.2, Widget 3.
 *
 * 🔑 Sıralama DİKKAT GEREKENE göre: kartı olmayan ve askıda olan çalışanlar
 * üstte. Alfabetik sıralasaydık sorunlu satır ikinci sayfada kalırdı.
 */
class CalisanDurumu extends Widget
{
    protected string $view = 'filament.kurum.widgets.calisan-durumu';

    protected static ?int $sort = 2;

    private const LIMIT = 5;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::user()?->kurum !== null && static::calisanlar()->isNotEmpty();
    }

    /** @return Collection<int, User> */
    public static function calisanlar(): Collection
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kurum = Auth::user()?->kurum;

        if (! $kurum instanceof Kurum) {
            return self::$onbellek = collect();
        }

        return self::$onbellek = User::with('akreditasyon')
            ->where('kurum_id', $kurum->getKey())     // 🔒 kapsam
            ->whereKeyNot(Auth::id())
            ->whereNull('ayrildi_at')
            ->get()
            /*
             * Sıralama BELLEKTE: "kartı yok" bilgisi ilişkiden geliyor, tek
             * sorguyla ORDER BY'a çevirmek karmaşık bir alt sorgu gerektirir.
             * Kurum başına çalışan sayısı onlarla ölçülür, maliyet önemsiz.
             */
            ->sortBy(fn (User $k) => [
                match (true) {
                    $k->akreditasyon === null => 0,
                    $k->akreditasyon->durum !== AkreditasyonDurumu::Aktif => 1,
                    default => 2,
                },
                $k->name,
            ])
            ->take(self::LIMIT)
            ->values();
    }

    /** @return Collection<int, User> */
    public function getCalisanlarProperty(): Collection
    {
        return static::calisanlar();
    }

    public function getToplamProperty(): int
    {
        $kurum = $this->kurumu();

        return $kurum === null ? 0 : User::where('kurum_id', $kurum->getKey())
            ->whereKeyNot(Auth::id())
            ->whereNull('ayrildi_at')
            ->count();
    }

    private function kurumu(): ?Kurum
    {
        return Auth::user()?->kurum;
    }
}
