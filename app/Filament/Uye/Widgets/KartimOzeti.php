<?php

namespace App\Filament\Uye\Widgets;

use App\Models\Akreditasyon;
use App\Support\KartDurumu;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Üye panosunun ilk kutusu: "kartım geçerli mi?" -- briefi md. B.1.
 *
 * 🔒 Yalnızca giriş yapan kişinin KENDİ akreditasyonu. Kapsam
 * `kullanici_id = Auth::id()` ile sorguda daraltılır; ekranda değil.
 */
class KartimOzeti extends Widget
{
    protected string $view = 'filament.uye.widgets.kartim-ozeti';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /** Akreditasyonu olmayan (yeni onaylanmış, kartı henüz yok) da görsün. */
    public static function canView(): bool
    {
        return Auth::check();
    }

    public function getAkreditasyonProperty(): ?Akreditasyon
    {
        return Akreditasyon::with(['guncelKart', 'kurum'])
            ->where('kullanici_id', Auth::id())
            ->latest('id')
            ->first();
    }

    public function getGorselAdresiProperty(): ?string
    {
        $kart = $this->akreditasyon?->guncelKart;

        return $kart?->gorsel_yolu ? route('kart.gorsel', $kart) : null;
    }

    public function getUyariProperty(): ?array
    {
        return KartDurumu::uyari($this->akreditasyon);
    }

    public function getDurumMesajiProperty(): ?string
    {
        return KartDurumu::mesaj($this->akreditasyon);
    }

    public function getKalanGunProperty(): ?int
    {
        return KartDurumu::kalanGun($this->akreditasyon);
    }
}
