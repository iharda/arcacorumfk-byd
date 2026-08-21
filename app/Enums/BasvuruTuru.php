<?php

namespace App\Enums;

/**
 * Basvuru turleri -- Plan v1.0 md.3.
 * Kart numarasindaki tur kodu buradan gelir: [YIL]-[TUR]-[SIRA] ornegin 2026-K-0042.
 * ⚠️ Tur kodlari musteriyle kesinlestirilecek (Plan v1.0 md.6 dipnotu).
 */
enum BasvuruTuru: string
{
    case Kurum = 'kurum';
    case BasinMensubu = 'basin_mensubu';
    case IcerikUreticisi = 'icerik_ureticisi';

    public function etiket(): string
    {
        return match ($this) {
            self::Kurum => 'Kurumsal başvuru',
            self::BasinMensubu => 'Basın mensubu',
            self::IcerikUreticisi => 'İçerik üreticisi',
        };
    }

    /** Kart numarasindaki tur harfi. Kurum basvurusundan kart cikmaz. */
    public function kartKodu(): ?string
    {
        return match ($this) {
            self::Kurum => null,
            self::BasinMensubu => 'K',   // kurum calisani
            self::IcerikUreticisi => 'I',
        };
    }

    /** Kurum secimi zorunlu mu? */
    public function kurumGerektirir(): bool
    {
        return $this === self::BasinMensubu;
    }
}
