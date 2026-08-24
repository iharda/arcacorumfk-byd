<?php

namespace App\Rules;

use App\Support\Telefon;
use App\Support\UlkeKodu;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Telefon doğrulaması -- Revizyon md.5.2.
 *
 * Türkiye için kesin kural, diğer ülkeler için gevşek: yabancı numaraların
 * hane kuralını burada tutmak, yanlış reddetmeye ve sürekli bakıma yol açar.
 *
 * 🪤 Kurum telefonu SABİT HAT olabilir (0364 …). "5 ile başlar" kuralını her
 * alana uygulamak kurumların yarısını reddederdi; cep zorunluluğu alan bazında
 * seçilir.
 */
class TelefonNumarasi implements ValidationRule
{
    public function __construct(
        private string $ulkeAlani = 'telefon_ulke',
        private bool $cep = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ulke = (string) request()->input($this->ulkeAlani, UlkeKodu::VARSAYILAN);

        if (! UlkeKodu::gecerliMi($ulke)) {
            $fail('Ülke kodu geçersiz.');

            return;
        }

        $rakam = Telefon::yerelRakamlar((string) $value, $ulke);

        if ($ulke !== UlkeKodu::VARSAYILAN) {
            if (strlen($rakam) < 6 || strlen($rakam) > 15) {
                $fail('Telefon numarası geçersiz.');
            }

            return;
        }

        // Türkiye: 10 hane. Cep 5 ile, sabit hat 2-4 ile başlar.
        $desen = $this->cep ? '/^5\d{9}$/' : '/^[2-5]\d{9}$/';

        if (preg_match($desen, $rakam) !== 1) {
            $fail($this->cep
                ? 'Geçerli bir cep telefonu girin. Örnek: 532 123 45 67'
                : 'Geçerli bir telefon numarası girin. Örnek: 364 213 45 67');
        }
    }
}
