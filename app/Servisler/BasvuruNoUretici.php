<?php

namespace App\Servisler;

use App\Models\Basvuru;

/**
 * Basvurana verilen numara: `2026-BV-0137` (Cuneyt Bey, 03.09.2026 ekran
 * kaydi 00:19 -- "buna 'no' derken bu ne? Kart no gibi normal olacak").
 *
 * 🪤 Onceki bicim dort karakterlik RASTGELE koddu (KR7R, ZB25). Gerekcesi
 * hâlâ gecerli -- 26 haneli ULID telefonda okunmuyordu (Yusuf/IT, 27.08.2026)
 * -- ama rastgele kod ne SIRALANIR ne de bir duzen tasir. Yeni bicim ikisini
 * de veriyor ve kart numarasiyla ayni dili konusuyor.
 *
 * 🔒 ULID'in YERINI ALMAZ. Rota baglamasi ve butun ic bagintilar `ulid`
 * uzerinden yurur (Plan v1.0 md.11 "tum ID'ler tahmin edilemez"); sirali
 * numara TAHMIN EDILEBILIR, bu yuzden basvuru_no hicbir adreste ya da
 * yetkilendirme kararinda kullanilmaz -- yalnizca insan gozu icin bir etiket.
 */
class BasvuruNoUretici
{
    /**
     * Basvurunun tur kodu SABIT: kart harfleri gibi ayardan gelmiyor.
     * Numara turden bagimsiz tek bir seri; kurumsal ve bireysel basvurular
     * ayni kuyrukta bekliyor, iki ayri sayac yetkiliye bir sey soylemezdi.
     */
    public const KOD = 'BV';

    public function __construct(private SiraliNo $siraliNo) {}

    /**
     * Numarayi verir ve KAYDEDER. Numarasi olan basvuruya dokunmaz:
     * duzeltmeden donen basvuru yeniden gonderiliyor ama AYNI basvuru --
     * basvuranin elindeki numara degismemeli.
     */
    public function ver(Basvuru $basvuru): void
    {
        if (filled($basvuru->basvuru_no)) {
            return;
        }

        // 🕐 Yil ISTANBUL'a gore: panel tarihleri de Istanbul'a gore
        // gosteriliyor, 1 Ocak 01:00'de gonderilen basvuru 2026 tarihi
        // gosterip 2025 numarasi tasimasin (uygulama saat dilimi UTC).
        $yil = now()->timezone('Europe/Istanbul')->year;

        $this->siraliNo->uret(
            'Başvuru numarası',
            $yil,
            self::KOD,
            fn () => $this->sonrakiSira($yil),
            function (string $no, int $sira) use ($basvuru, $yil) {
                $basvuru->fill(['basvuru_no' => $no, 'no_yil' => $yil, 'no_sira' => $sira])->save();

                return $basvuru;
            },
        );
    }

    private function sonrakiSira(int $yil): int
    {
        // 💣 withTrashed SART: silinen basvurunun numarasi tekrar dagitilirsa
        // arsiv/denetim kaydinda iki farkli basvuru ayni numarayi tasir.
        // Benzersiz kisit da silinenleri kapsiyor.
        return (int) Basvuru::withTrashed()->where('no_yil', $yil)->max('no_sira') + 1;
    }
}
