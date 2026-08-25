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

    /**
     * Kart numarasindaki tur harfinin VARSAYILANI. Kurum basvurusundan kart
     * cikmaz, o yuzden null.
     *
     * ⚠️ Asil kaynak "kart_tur_kodlari" ayaridir; KartNoUretici::kod() once
     * ona bakar. Buradakiler yalnizca ayar bosken gecerlidir.
     *
     * Neden K ve B: `2026-K-0042` Plan v1.0'daki ornekle birebir. Icerik
     * ureticisi icin `I` DEGIL `B` (bagimsiz) — kart no kapida GOZLE okunuyor
     * ve `I` harfi `1` rakamiyla karisiyor.
     */
    public function kartKodu(): ?string
    {
        return match ($this) {
            self::Kurum => null,
            self::BasinMensubu => 'K',   // kurum calisani
            self::IcerikUreticisi => 'B', // bagimsiz
        };
    }

    /**
     * Bu türün kamuya açık başvuru formu. Üç yerde aynı `match` yazılıyordu
     * (BasvurumSayfasi, red bildirimi); tek yerde dursun.
     */
    public function basvuruRotasi(): string
    {
        return route(match ($this) {
            self::Kurum => 'basvuru.kurum',
            self::BasinMensubu => 'basvuru.basin-mensubu',
            self::IcerikUreticisi => 'basvuru.icerik-ureticisi',
        });
    }

    /** Kurum secimi zorunlu mu? */
    public function kurumGerektirir(): bool
    {
        return $this === self::BasinMensubu;
    }
}
