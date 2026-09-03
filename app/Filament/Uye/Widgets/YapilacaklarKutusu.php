<?php

namespace App\Filament\Uye\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * "Benden bir şey bekleniyor mu?" -- briefi md. B.1, Widget 2.
 *
 * 🔑 İş YOKSA KUTU DA YOK (md. B.0.2): boş kutu, dolu kutudan kötüdür.
 * `canView()` statik olduğu için liste statik üretilir ve istek başına
 * bir kez hesaplanır (aynı istekte render da aynı listeyi okur).
 *
 * 🚫 2FA satırı BİLEREK YOK: üye panelinde iki adımlı doğrulama zorunlu
 * değil, her girişte "kur" demek gürültü olurdu.
 */
class YapilacaklarKutusu extends Widget
{
    protected string $view = 'filament.uye.widgets.yapilacaklar-kutusu';

    protected static ?int $sort = 1;

    /** İstek başına hesaplanmış liste; canView() ve render aynı sonucu görsün. */
    private static ?array $onbellek = null;

    public static function canView(): bool
    {
        return Auth::check() && static::isler() !== [];
    }

    /**
     * @return array<int, array{metin: string, adres: ?string, renk: string, ikon: string}>
     */
    public static function isler(): array
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kullanici = Auth::user();

        if (! $kullanici instanceof User) {
            return self::$onbellek = [];
        }

        $isler = [];

        if ($kullanici->email_verified_at === null) {
            $isler[] = [
                'metin' => 'E-posta adresiniz doğrulanmadı. Doğrulama bağlantısını yeniden gönderin.',
                'adres' => route('filament.uye.auth.email-verification.prompt'),
                'renk' => 'warning',
                'ikon' => 'heroicon-m-envelope',
            ];
        }

        /*
         * 🔴 Açık düzeltme talebi en acil satır: başvuran cevap vermezse
         * başvurusu ilerlemez ve bunu başka hiçbir ekranda görmez.
         */
        $acikDuzeltmesiOlan = Basvuru::with('duzeltmeler')
            ->where('kullanici_id', $kullanici->getKey())
            ->latest('id')
            ->first();

        if ($acikDuzeltmesiOlan?->acikDuzeltme() !== null) {
            $isler[] = [
                'metin' => 'Başvurunuz için düzeltme talebi var; e-postanızdaki bağlantıdan tamamlayın.',
                'adres' => route('filament.uye.pages.basvurum'),
                'renk' => 'danger',
                'ikon' => 'heroicon-m-exclamation-triangle',
            ];
        }

        $eksikAlanlar = collect(['telefon' => 'telefon', 'il' => 'il', 'ilce' => 'ilçe'])
            ->filter(fn (string $etiket, string $alan) => blank($kullanici->getAttribute($alan)))
            ->values();

        if ($eksikAlanlar->isNotEmpty()) {
            $isler[] = [
                'metin' => 'Profilinizde eksik bilgi var ('.$eksikAlanlar->implode(', ').').',
                'adres' => route('filament.uye.auth.profile'),
                'renk' => 'gray',
                'ikon' => 'heroicon-m-user-circle',
            ];
        }

        $askida = Akreditasyon::where('kullanici_id', $kullanici->getKey())
            ->where('durum', AkreditasyonDurumu::Askida->value)
            ->latest('id')
            ->first();

        if ($askida !== null) {
            $isler[] = [
                'metin' => 'Akreditasyonunuz askıda'
                    .(filled($askida->iptal_nedeni) ? ': '.$askida->iptal_nedeni : '.')
                    .' Askı kaldırılana kadar kart geçerli değildir.',
                'adres' => route('filament.uye.pages.kartim'),
                'renk' => 'danger',
                'ikon' => 'heroicon-m-pause-circle',
            ];
        }

        return self::$onbellek = $isler;
    }

    public function getIslerProperty(): array
    {
        return static::isler();
    }
}
