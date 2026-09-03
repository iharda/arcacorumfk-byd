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
            self::IcerikUreticisi => 'Bağımsız içerik üreticisi',
        };
    }

    /**
     * "... basvurunuz" cumlesinde kullanilan hali.
     *
     * 💥 etiket() BURAYA UYMAZ: Kurum turunun etiketi zaten "Kurumsal basvuru",
     * cumleye konunca "Kurumsal basvuru basvurunuz" cikiyordu -- musteri bunu
     * gonderilen e-postada gordu (Yusuf/IT, 2026-08-27).
     *
     * Kurum icin sifat KUYRUKTAKI etiketten de farkli: yetkili tabloda
     * "Kurumsal basvuru" gormek istiyor, basvuran e-postasinda ise
     * "Medya kurulusu basvurunuz" (Cuneyt Bey revizyonu, 03.09.2026).
     */
    public function basvuruSifati(): string
    {
        return match ($this) {
            self::Kurum => 'Medya kuruluşu',
            self::BasinMensubu => 'Basın mensubu',
            self::IcerikUreticisi => 'Bağımsız içerik üreticisi',
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
