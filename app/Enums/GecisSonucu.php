<?php

namespace App\Enums;

/** Turnike/gise okutma sonucu -- her okutma sonucu ne olursa olsun loglanir. */
enum GecisSonucu: string
{
    case Izinli         = 'izinli';
    case Askida         = 'askida';
    case Iptal          = 'iptal';
    case Bulunamadi     = 'bulunamadi';
    case ImzaGecersiz   = 'imza_gecersiz';
    case BolgeYetkisiYok = 'bolge_yetkisi_yok';
    case MukerrerOkutma = 'mukerrer_okutma';

    public function etiket(): string
    {
        return match ($this) {
            self::Izinli          => 'İzinli',
            self::Askida          => 'Askıda',
            self::Iptal           => 'İptal edilmiş',
            self::Bulunamadi      => 'Kayıt bulunamadı',
            self::ImzaGecersiz    => 'İmza geçersiz',
            self::BolgeYetkisiYok => 'Bölge yetkisi yok',
            self::MukerrerOkutma  => 'Mükerrer okutma',
        };
    }

    public function basarili(): bool
    {
        return $this === self::Izinli;
    }
}
