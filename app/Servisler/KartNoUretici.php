<?php

namespace App\Servisler;

use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Kart numarası -- Plan v1.0 md.6: `[YIL]-[TÜR]-[SIRA]` → 2026-K-0042.
 *
 * ⚠️ Tür kodları müşteriyle KESİNLEŞMEDİ (planın kendi dipnotu). Kodlar
 * BasvuruTuru::kartKodu() içinde tek yerde; değişirse orayı düzelt.
 *
 * 🔒 Sıra numarası eşzamanlı iki onayda ÇAKIŞMAMALI. Veritabanında
 * (yil, tur_kodu, sira) benzersiz; çakışma olursa yeniden deniyoruz —
 * uygulama seviyesinde kilit tutmaktan daha güvenilir, çünkü asıl güvence
 * veritabanı kısıtı.
 */
class KartNoUretici
{
    private const AZAMI_DENEME = 5;

    public function uret(BasvuruTuru $tur, callable $kaydet): Akreditasyon
    {
        // ⚠️ Enum'daki varsayılana DEĞİL, ayara bak: kulüp harfi panelden
        // değiştirebiliyor.
        $kod = self::kod($tur)
            ?? throw new RuntimeException('Bu başvuru türünden kart üretilmez: '.$tur->value);

        $yil = (int) (Ayar::al('kart_yil') ?: now()->year);

        for ($deneme = 1; $deneme <= self::AZAMI_DENEME; $deneme++) {
            $sira = $this->sonrakiSira($yil, $kod);

            try {
                return $kaydet([
                    'kart_no' => sprintf('%d-%s-%04d', $yil, $kod, $sira),
                    'yil' => $yil,
                    'tur_kodu' => $kod,
                    'sira' => $sira,
                ]);
            } catch (QueryException $e) {
                // 23505 = unique_violation (PostgreSQL). MySQL'de 23000.
                if (! in_array($e->getCode(), ['23505', '23000'], true) || $deneme === self::AZAMI_DENEME) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Kart numarası üretilemedi.');
    }

    /**
     * Türün kart harfi. Önce ayar, sonra enum'daki varsayılan.
     * Ayar panelden değiştirilebilir; kulüp "biz B diyoruz" derse kod
     * değişikliği gerekmez.
     */
    public static function kod(BasvuruTuru $tur): ?string
    {
        if ($tur->kartKodu() === null) {
            return null;   // kurumsal başvurudan kart çıkmaz
        }

        $ayar = (array) Ayar::al('kart_tur_kodlari', []);

        return strtoupper(trim((string) ($ayar[$tur->value] ?? ''))) ?: $tur->kartKodu();
    }

    private function sonrakiSira(int $yil, string $kod): int
    {
        return (int) Akreditasyon::query()
            ->where('yil', $yil)
            ->where('tur_kodu', $kod)
            ->max('sira') + 1;
    }
}
