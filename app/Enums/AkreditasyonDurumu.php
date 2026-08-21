<?php

namespace App\Enums;

/**
 * Akreditasyon durumu -- Plan v1.0 md.4.
 * 🔑 Yetki bilgisi KARTTA DEGIL burada. Turnike her okutmada bunu sorar,
 * bu yuzden iptal ANINDA etkilidir ve kart geri toplanmaz.
 */
enum AkreditasyonDurumu: string
{
    case Aktif  = 'aktif';
    case Askida = 'askida';
    case Iptal  = 'iptal';

    public function etiket(): string
    {
        return match ($this) {
            self::Aktif  => 'Aktif',
            self::Askida => 'Askıda',
            self::Iptal  => 'İptal',
        };
    }

    public function renk(): string
    {
        return match ($this) {
            self::Aktif  => 'success',
            self::Askida => 'warning',
            self::Iptal  => 'danger',
        };
    }

    /** SADECE Aktif turnikeden gecer. */
    public function gecebilirMi(): bool
    {
        return $this === self::Aktif;
    }
}
