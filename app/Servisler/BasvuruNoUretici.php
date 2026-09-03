<?php

namespace App\Servisler;

use App\Models\Basvuru;
use RuntimeException;

/**
 * Basvuranin e-postada gordugu, telefonda okuyabildigi KISA numara (4 karakter).
 * Musteri istegi (Yusuf/IT, 2026-08-27): bildirimdeki 26 haneli ULID okunmuyordu.
 *
 * 🔒 ULID'in YERINI ALMAZ. Rota baglamasi ve butun ic bagintilar `ulid`
 * uzerinden yurur (Plan v1.0 md.11 "tum ID'ler tahmin edilemez"); 4 karakter
 * KABA KUVVETLE denenebilir, bu yuzden basvuru_no HICBIR adreste ya da
 * yetkilendirme kararinda kullanilmaz -- yalnizca insan gozu icin bir etikettir.
 */
class BasvuruNoUretici
{
    /**
     * 🔤 Sesli harf YOK: Turkce'de her hece sesli harf ister, sesliler cikinca
     * uretilen dizi hicbir kelimeye -- ozellikle kufre -- benzemez. Ustune
     * karisan karakterler de atildi: 0/O, 1/I/L. Geriye 28 isaret kaliyor
     * (kart numarasinda `I` yerine `B` secilmesiyle ayni gerekce).
     */
    public const ALFABE = '23456789BCDFGHJKMNPQRSTVWXYZ';

    public const UZUNLUK = 4;

    /**
     * 28^4 = 614.656 olasilik. Birkac bin basvuruda carpisma binde bir
     * mertebesinde; yine de denenir, son guvence veritabanindaki benzersiz
     * kisit (goc dosyasi).
     */
    private const AZAMI_DENEME = 20;

    public function uret(): string
    {
        for ($deneme = 1; $deneme <= self::AZAMI_DENEME; $deneme++) {
            $no = self::rastgele();

            // 💣 withTrashed SART: silinen basvurunun numarasi tekrar
            // dagitilirsa arsiv/denetim kaydinda iki farkli basvuru ayni
            // numarayi tasir. Benzersiz kisit da silinenleri kapsiyor.
            if (! Basvuru::withTrashed()->where('basvuru_no', $no)->exists()) {
                return $no;
            }
        }

        throw new RuntimeException('Başvuru numarası üretilemedi.');
    }

    public static function rastgele(): string
    {
        $son = strlen(self::ALFABE) - 1;
        $no = '';

        for ($i = 0; $i < self::UZUNLUK; $i++) {
            // 🔒 rand() DEGIL random_int(): tahmin edilebilirlik burada da
            // istenmiyor, numara e-postayla gidiyor.
            $no .= self::ALFABE[random_int(0, $son)];
        }

        return $no;
    }
}
