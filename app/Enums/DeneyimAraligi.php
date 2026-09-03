<?php

namespace App\Enums;

/**
 * Basin mensubunun medya sektorundeki deneyimi -- Cuneyt Bey revizyonu
 * (03.09.2026): "Bu kisimda limitler olmali - 1-5, 5-10 gibi".
 *
 * Serbest rakam yerine aralik: kimse deneyimini "7 yil" diye tarif etmiyor,
 * kulup de karsilastirilabilir veri aliyor. `CalisanAraligi` ile ayni kalip.
 *
 * 🪤 GERIYE DONUK: eski basvurularin `form_verisi['calisma_yili']` alaninda
 * DUZ RAKAM var. `sayidan()` onlari ekranda araliga cevirir; veriye
 * dokunulmaz.
 */
enum DeneyimAraligi: string
{
    case BirAlti = '0-1';
    case Bes = '1-5';
    case On = '6-10';
    case Yirmi = '11-20';
    case YirmiUstu = '20+';

    public function etiket(): string
    {
        return match ($this) {
            self::BirAlti => '1 yıldan az',
            self::YirmiUstu => '20 yıl ve üzeri',
            default => $this->value.' yıl',
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
            $sayi < 1 => self::BirAlti,
            $sayi <= 5 => self::Bes,
            $sayi <= 10 => self::On,
            $sayi <= 20 => self::Yirmi,
            default => self::YirmiUstu,
        };
    }

    /**
     * Kayitli degerin -- aralik ya da eski rakam -- okunur hali.
     * Bos deger `null` doner; cagiran yer "—" basar.
     */
    public static function goster(mixed $deger): ?string
    {
        if ($deger === null || $deger === '') {
            return null;
        }

        if (is_string($deger) && ($aralik = self::tryFrom($deger)) !== null) {
            return $aralik->etiket();
        }

        return is_numeric($deger)
            ? (self::sayidan((int) $deger)?->etiket() ?? $deger.' yıl')
            : (string) $deger;
    }
}
