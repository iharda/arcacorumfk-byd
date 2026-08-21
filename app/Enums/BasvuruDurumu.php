<?php

namespace App\Enums;

/**
 * Basvuru durum makinesi -- Plan v1.0 md.4.
 * Gecisler gecebilirMi() ile korunur; modelde ve policy'de TEK kaynak burasi.
 */
enum BasvuruDurumu: string
{
    case Taslak = 'taslak';
    case Gonderildi = 'gonderildi';
    case Incelemede = 'incelemede';
    case EksikEvrak = 'eksik_evrak';
    case Onaylandi = 'onaylandi';
    case Reddedildi = 'reddedildi';

    public function etiket(): string
    {
        return match ($this) {
            self::Taslak => 'Taslak',
            self::Gonderildi => 'Gönderildi',
            self::Incelemede => 'İncelemede',
            self::EksikEvrak => 'Eksik evrak',
            self::Onaylandi => 'Onaylandı',
            self::Reddedildi => 'Reddedildi',
        };
    }

    public function renk(): string
    {
        return match ($this) {
            self::Taslak => 'gray',
            self::Gonderildi => 'gray',
            self::Incelemede => 'info',
            self::EksikEvrak => 'warning',
            self::Onaylandi => 'success',
            self::Reddedildi => 'danger',
        };
    }

    /** @return array<int, self> */
    public function sonrakiler(): array
    {
        return match ($this) {
            self::Taslak => [self::Gonderildi],
            self::Gonderildi => [self::Incelemede],
            self::Incelemede => [self::EksikEvrak, self::Onaylandi, self::Reddedildi],
            self::EksikEvrak => [self::Gonderildi],
            self::Onaylandi, self::Reddedildi => [],
        };
    }

    public function gecebilirMi(self $hedef): bool
    {
        return in_array($hedef, $this->sonrakiler(), true);
    }

    /** Yetkili kuyrugunda gorunmesi gerekenler. */
    public static function kuyruk(): array
    {
        return [self::Gonderildi, self::Incelemede, self::EksikEvrak];
    }
}
