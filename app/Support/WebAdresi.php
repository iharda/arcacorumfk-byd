<?php

namespace App\Support;

/**
 * Kullanicinin yazdigi web adresini kabul edilebilir hale getirir --
 * Cuneyt Bey revizyonu (03.09.2026): "Bu sekilde kabul edilmeli,
 * http vs yazmaya zorlamamaliyiz."
 *
 * 💀 Alanlar `type="url"` idi ve TARAYICI "Lütfen bir URL girin." diyerek
 * formu gondermiyordu. `aybers.com` yazan basvuran hatanin ne oldugunu
 * anlamiyordu: kutuda gecerli bir adres var, eksik olan sema.
 *
 * 🪤 Duzeltme SUNUCUDA yapilir (`prepareForValidation`), istemcide degil:
 * JS kapaliyken de, dogrudan POST edildiginde de ayni sonuc cikmali.
 * Alan tipi artik `text`; `url` dogrulamasi semayi tamamladiktan SONRA
 * calisiyor, yani gercekten bozuk adres hala eleniyor.
 */
class WebAdresi
{
    /**
     * Sema yoksa `https://` ekler. Bos deger bos kalir; `mailto:` gibi
     * bilinmeyen semalara DOKUNULMAZ -- oradaki hatayi `url` kurali soylesin.
     */
    public static function duzelt(mixed $ham): mixed
    {
        if (! is_string($ham)) {
            return $ham;
        }

        $deger = trim($ham);

        if ($deger === '') {
            return $deger;
        }

        // Zaten bir sema var mi? (`http:`, `https:`, `ftp:` …)
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $deger) === 1) {
            return $deger;
        }

        // `//ornek.com` -- sema-bagimsiz yazim.
        if (str_starts_with($deger, '//')) {
            return 'https:'.$deger;
        }

        // `mailto:...` gibi sema iceren ama `//` icermeyen yazimlara dokunma.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $deger) === 1) {
            return $deger;
        }

        return 'https://'.$deger;
    }

    /**
     * Bir dizinin TUM elemanlarini duzeltir (sosyal medya kutulari gibi).
     *
     * @param  array<array-key, mixed>  $degerler
     * @return array<array-key, mixed>
     */
    public static function dizi(array $degerler): array
    {
        return array_map(fn ($d) => self::duzelt($d), $degerler);
    }
}
