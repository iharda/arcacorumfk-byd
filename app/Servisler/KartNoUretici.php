<?php

namespace App\Servisler;

use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use RuntimeException;

/**
 * Kart numarası -- Plan v1.0 md.6: `[YIL]-[TÜR]-[SIRA]` → 2026-K-0042.
 *
 * ⚠️ Tür kodları müşteriyle KESİNLEŞMEDİ (planın kendi dipnotu). Kodlar
 * BasvuruTuru::kartKodu() içinde tek yerde; değişirse orayı düzelt.
 *
 * 🔒 Biçim ve çakışma yönetimi SiraliNo'da; başvuru numarası da (2026-BV-0137)
 * aynı mekaniği kullanıyor. Burada kalan tek iş: hangi yıl, hangi harf ve
 * sıranın nereden sayılacağı.
 */
class KartNoUretici
{
    public function __construct(private SiraliNo $siraliNo) {}

    /**
     * @param  callable(array<string, mixed>): Akreditasyon  $kaydet
     */
    public function uret(BasvuruTuru $tur, callable $kaydet): Akreditasyon
    {
        // ⚠️ Enum'daki varsayılana DEĞİL, ayara bak: kulüp harfi panelden
        // değiştirebiliyor.
        $kod = self::kod($tur)
            ?? throw new RuntimeException('Bu başvuru türünden kart üretilmez: '.$tur->value);

        $yil = (int) (Ayar::al('kart_yil') ?: now()->year);

        return $this->siraliNo->uret(
            'Kart numarası',
            $yil,
            $kod,
            fn () => $this->sonrakiSira($yil, $kod),
            fn (string $no, int $sira) => $kaydet([
                'kart_no' => $no,
                'yil' => $yil,
                'tur_kodu' => $kod,
                'sira' => $sira,
            ]),
        );
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
