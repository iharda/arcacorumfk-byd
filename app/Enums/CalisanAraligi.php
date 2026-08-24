<?php

namespace App\Enums;

/**
 * Kurumun calisan sayisi -- Revizyon md.5.4.
 *
 * Serbest rakam yerine aralik: basvuran "tam kac kisi" diye dusunmez, kulup de
 * karsilastirabilir veri alir. Esikler migration'daki CASE ile AYNI olmali.
 */
enum CalisanAraligi: string
{
    case Bes = '1-5';
    case On = '6-10';
    case Yirmi = '11-20';
    case Elli = '21-50';
    case ElliUstu = '50+';

    public function etiket(): string
    {
        return match ($this) {
            self::ElliUstu => '50 ve üzeri',
            default => $this->value.' kişi',
        };
    }

    /** @return array<string, string> selectbox secenekleri: deger => etiket */
    public static function secenekler(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $aralik) => [$aralik->value => $aralik->etiket()])
            ->all();
    }

    /** Eski serbest rakami araliga cevirir (gecmis veri ve ice aktarim icin). */
    public static function sayidan(?int $sayi): ?self
    {
        return match (true) {
            $sayi === null => null,
            $sayi <= 5 => self::Bes,
            $sayi <= 10 => self::On,
            $sayi <= 20 => self::Yirmi,
            $sayi <= 50 => self::Elli,
            default => self::ElliUstu,
        };
    }
}
