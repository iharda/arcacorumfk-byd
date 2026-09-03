<?php

namespace App\Filament\Uye\Widgets;

use App\Models\Akreditasyon;
use App\Models\GecisKaydi;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Kişinin KENDİ son 5 geçişi -- briefi md. B.1, Widget 5.
 *
 * Değeri: turnikede sorun yaşayan kişi "sistem beni okudu mu" sorusunu kendi
 * görür; çağrı merkezi trafiği düşer.
 *
 * 🔒 Sorgu kişinin KENDİ akreditasyonlarıyla sınırlanır. README notu
 * "kendi kaydını görmek için yetki aranmaz" der -- ama önce SAHİPLİK.
 * 🪤 `akreditasyon()` ilişkisi yalnızca EN YENİYİ verir; yeniden başvuran
 * kişinin eski akreditasyonuna ait geçişler kaybolmasın diye `akreditasyonlar`
 * (çoğul) kullanılıyor.
 */
class SonGecislerim extends Widget
{
    protected string $view = 'filament.uye.widgets.son-gecislerim';

    protected static ?int $sort = 5;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return Auth::check() && static::kayitlar()->isNotEmpty();
    }

    /** @return Collection<int, GecisKaydi> */
    public static function kayitlar(): Collection
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kimlikler = Akreditasyon::where('kullanici_id', Auth::id())->pluck('id');

        return self::$onbellek = $kimlikler->isEmpty()
            ? collect()
            : GecisKaydi::with('kapiIstemcisi')
                ->whereIn('akreditasyon_id', $kimlikler)
                ->orderByDesc('okundu_at')
                ->limit(5)
                ->get();
    }

    /** @return Collection<int, GecisKaydi> */
    public function getKayitlarProperty(): Collection
    {
        return static::kayitlar();
    }
}
