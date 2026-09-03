<?php

namespace App\Support;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;

/**
 * Kart durumunun insan diline çevrilmiş hâli -- TEK kaynak.
 *
 * 💀 Bu metin eskiden yalnızca `Uye\Pages\Kartim::durumMesaji()` içindeydi.
 * Pano da aynı cümleleri gösterecek; kopyalansaydı biri düzeltilip diğeri
 * unutulurdu ve kullanıcı iki ekranda iki farklı şey okurdu.
 */
class KartDurumu
{
    /** Kartla ilgili söylenmesi gereken varsa cümlesi; yoksa null. */
    public static function mesaj(?Akreditasyon $akreditasyon): ?string
    {
        if (! $akreditasyon) {
            return 'Henüz akreditasyonunuz yok. Başvurunuz onaylandığında kartınız burada görünür.';
        }

        if ($akreditasyon->durum === AkreditasyonDurumu::Iptal) {
            return 'Akreditasyonunuz iptal edilmiştir; kart kulüp girişlerinde geçerli değildir.';
        }

        if ($akreditasyon->durum === AkreditasyonDurumu::Askida) {
            return 'Akreditasyonunuz askıdadır; askı kaldırılana kadar kart geçerli değildir.';
        }

        if (! $akreditasyon->guncelKart) {
            return 'Kartınız hazırlanıyor. Birkaç dakika içinde burada görünecek.';
        }

        return null;
    }

    /**
     * Panodaki uyarı şeridi: `['renk' => …, 'metin' => …]` ya da null.
     *
     * Askı/iptal KIRMIZI ve sebebi yazılı; geçerlilik 30 günden yakınsa
     * turuncu. Maç günü sabahı "kartım geçerli mi" sorusunun cevabı budur.
     */
    public static function uyari(?Akreditasyon $akreditasyon): ?array
    {
        if (! $akreditasyon) {
            return null;
        }

        if (in_array($akreditasyon->durum, [AkreditasyonDurumu::Iptal, AkreditasyonDurumu::Askida], true)) {
            return [
                'renk' => 'danger',
                'metin' => trim(self::mesaj($akreditasyon).' '.($akreditasyon->iptal_nedeni ?? '')),
            ];
        }

        $kalan = self::kalanGun($akreditasyon);

        if ($kalan === null || $kalan > 30) {
            return null;
        }

        return [
            'renk' => 'warning',
            'metin' => $kalan < 0
                ? 'Kartınızın geçerlilik süresi doldu. Yenileme için kulüple iletişime geçin.'
                : "Kartınızın geçerliliği {$kalan} gün sonra doluyor.",
        ];
    }

    /** Bugünden bitişe kalan tam gün; tarih yoksa null. */
    public static function kalanGun(?Akreditasyon $akreditasyon): ?int
    {
        $bitis = $akreditasyon?->gecerlilik_bitis;

        return $bitis === null
            ? null
            : (int) now('Europe/Istanbul')->startOfDay()->diffInDays($bitis->copy()->startOfDay(), false);
    }
}
