<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vergi kimlik numarası / T.C. kimlik numarası doğrulaması -- Revizyon md.5.3.
 *
 * Eski kural yalnızca HANE SAYIYORDU (`regex:/^\d{10,11}$/`): "1111111111" gibi
 * uydurma numaralar geçiyordu. İkisinin de algoritmik sağlaması var; şahıs
 * işletmeleri VKN yerine TCKN verdiği için ikisi de kabul edilir.
 */
class VergiNumarasi implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $n = preg_replace('/\D+/', '', (string) $value) ?? '';

        $gecerli = match (strlen($n)) {
            10 => $this->vknGecerliMi($n),
            11 => $this->tcknGecerliMi($n),
            default => false,
        };

        if (! $gecerli) {
            $fail('Vergi numarası geçersiz. 10 haneli vergi kimlik numarası veya 11 haneli T.C. kimlik numarası girin.');
        }
    }

    /**
     * VKN sağlama algoritması (GİB).
     *
     * 💀 Dolaşan kopyalarda İKİ hata var, ikisi de sessizce yanlış sonuç verir:
     *   1. "ara değer 0 ise 9 yaz" kuralı, dönüşümden ÖNCEKİ değere değil
     *      SONRAKİNE bakar: `temp != 0` iken `(temp * 2^k) % 9 == 0` çıkarsa
     *      (yalnızca temp = 9 olduğunda) 9 yazılır.
     *   2. `temp == 0` ise ara değer 0 KALIR, 9 olmaz.
     * Yanlış varyant `0000000000`'ı geçerli sayıyordu. Bu uygulama iki bağımsız
     * kütüphaneyle ve bilinen geçerli numaralarla (`1430466081`, `8790012345`)
     * karşılaştırılarak doğrulandı; birim testi `tests/Unit/VergiNumarasiTest`.
     */
    private function vknGecerliMi(string $n): bool
    {
        $toplam = 0;

        for ($i = 0; $i < 9; $i++) {
            $ara = ((int) $n[$i] + (9 - $i)) % 10;
            $donusum = ($ara * (2 ** (9 - $i))) % 9;

            if ($ara !== 0 && $donusum === 0) {
                $donusum = 9;
            }

            $toplam += $donusum;
        }

        return (10 - $toplam % 10) % 10 === (int) $n[9];
    }

    /** TCKN sağlama algoritması. */
    private function tcknGecerliMi(string $n): bool
    {
        if ($n[0] === '0') {
            return false;
        }

        $tek = (int) $n[0] + (int) $n[2] + (int) $n[4] + (int) $n[6] + (int) $n[8];
        $cift = (int) $n[1] + (int) $n[3] + (int) $n[5] + (int) $n[7];

        // 🪤 PHP'de (ve JS'te) negatif sayının modu NEGATİF çıkar: tek*7 - çift
        // eksiye düşen gerçek numaralar var, normalize edilmezse reddedilirler.
        $onuncu = ((($tek * 7 - $cift) % 10) + 10) % 10;
        $onbirinci = array_sum(array_map('intval', str_split(substr($n, 0, 10)))) % 10;

        return (int) $n[9] === $onuncu && (int) $n[10] === $onbirinci;
    }
}
