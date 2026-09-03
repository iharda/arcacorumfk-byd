<?php

namespace App\Servisler;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * `[YIL]-[KOD]-[SIRA]` numaralarinin ortak uretim mekanigi -- Plan v1.0 md.6.
 *
 * Iki numara ayni dili konusuyor: kart no `2026-K-0042`, basvuru no
 * `2026-BV-0137` (Cuneyt Bey, 03.09.2026 -- "kart no gibi normal olacak").
 * Bicim ve carpisma yonetimi TEK tanim; ikinci numarayi eklerken kopyalanan
 * dongu, bir sonraki hatanin iki yerde ayri ayri duzeltilmesi demekti.
 *
 * 🔒 Sira numarasi eszamanli iki cagrida CAKISMAMALI. Guvence uygulamada
 * degil VERITABANINDA: cagiran tablo kendi benzersiz kisitini koyar, cakisma
 * olursa burada yeniden denenir.
 *
 * 💀 PostgreSQL'de tekrar denemenin CALISMASI icin her deneme kendi isleminde
 * olmali. Bu servis cogunlukla bir islemin ICINDE cagriliyor ve PG'de bir hata
 * TUM islemi iptal (abort) eder: ikinci denemedeki ilk SELECT "25P02 current
 * transaction is aborted" firlatir, bu da 23505/23000 listesinde olmadigi icin
 * yeniden firlatilir. Yani dongu OLU KOD olur ve eszamanli iki cagri 500
 * verir (Yusuf/IT, 2026-08-23). Cozum: her deneme kendi DB::transaction'inda
 * -- distaki islemin icinde bu bir SAVEPOINT olur, hata yalnizca oraya kadar
 * geri sarilir ve islem kullanilabilir kalir. MySQL'de de ayni sekilde calisir.
 */
class SiraliNo
{
    private const AZAMI_DENEME = 5;

    /**
     * @template TSonuc
     *
     * @param  callable(): int  $sonrakiSira  o yilin siradaki numarasi
     * @param  callable(string, int): TSonuc  $kaydet  (numara, sira) -> kayit
     * @return TSonuc
     */
    public function uret(string $ad, int $yil, string $kod, callable $sonrakiSira, callable $kaydet): mixed
    {
        for ($deneme = 1; $deneme <= self::AZAMI_DENEME; $deneme++) {
            $sira = $sonrakiSira();
            $no = sprintf('%d-%s-%04d', $yil, $kod, $sira);

            try {
                // SAVEPOINT: cakisma distaki islemi degil yalnizca bu denemeyi
                // geri sarsin (yukaridaki 💀 notu).
                return DB::transaction(fn () => $kaydet($no, $sira));
            } catch (QueryException $e) {
                // 23505 = unique_violation (PostgreSQL). MySQL'de 23000.
                if (! in_array($e->getCode(), ['23505', '23000'], true) || $deneme === self::AZAMI_DENEME) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException($ad.' üretilemedi.');
    }
}
