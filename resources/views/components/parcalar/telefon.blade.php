{{-- Ülke kodu + maskeli telefon -- Revizyon md.5.2.

     Saklama biçimi E.164 (`+905321234567`); maske yalnızca yazarken yardımcı.
     🪤 Maske TÜRKİYE'ye özel: ülke değişince kaldırılır, yoksa yabancı numarayı
     10 haneye kırpardı. --}}
@props([
    'ad',
    'etiket' => 'Telefon',
    'ipucu' => '532 123 45 67',
    'sutun' => 1,
    // E.164 mevcut deger; duzeltme ekraninda kutu dolu gelir.
    'deger' => null,
    'yol' => null,
    // 💣 Köşeli parantezli adlarda ŞART. `alan[veri_telefon]_ulke` adını PHP
    // `alan[veri_telefon]` diye ayrıştırır ve ülke kodu numaranın ÜSTÜNE
    // yazılır: numara kaybolur, doğrulama "geçersiz" der.
    'ulkeAd' => null,
])

{{-- 🪤 `yol`: `old()` ve `$errors` NOKTA yolu ister. Girdi adı
     `alan[veri_telefon]` gibi köşeli parantezliyse `old('alan[veri_telefon]')`
     hiçbir zaman eşleşmez ve doğrulama hatasından sonra kutu BOŞALIR. --}}
@php
    $yol = $yol ?? $ad;
    // `+905321234567` -> ulke `+90`, yerel `5321234567`
    $mevcutUlke = App\Support\UlkeKodu::VARSAYILAN;
    $mevcutYerel = '';

    if (filled($deger)) {
        foreach (array_keys(App\Support\UlkeKodu::hepsi()) as $kodAdayi) {
            if (str_starts_with((string) $deger, $kodAdayi)) {
                $mevcutUlke = $kodAdayi;
                break;
            }
        }
        $mevcutYerel = App\Support\Telefon::yerelRakamlar((string) $deger, $mevcutUlke);
    }
@endphp

@php
    $ulkeAd = $ulkeAd ?? $ad.'_ulke';
    $ulkeYolu = $yol.'_ulke';
    $hata = $errors->first($yol) ?: $errors->first($ulkeYolu);
    $ortak = 'rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none';
    $cerceve = $hata ? 'border-kulup-600 bg-kulup-50' : 'border-neutral-300 bg-white';
@endphp

<div @class(['sm:col-span-2' => $sutun === 2])
     x-data="{ ulke: @js(old($ulkeYolu, $mevcutUlke)) }">
    <label for="{{ $ad }}" class="zorunlu block text-sm font-medium text-neutral-800">{{ $etiket }}</label>

    <div class="mt-1.5 flex gap-2">
        <select name="{{ $ulkeAd }}" x-model="ulke" aria-label="{{ $etiket }} ülke kodu"
                class="w-28 shrink-0 {{ $ortak }} {{ $cerceve }}">
            @foreach (App\Support\UlkeKodu::hepsi() as $kod => $kodEtiketi)
                <option value="{{ $kod }}" @selected(old($ulkeYolu, $mevcutUlke) === $kod)>
                    {{ $kodEtiketi }}
                </option>
            @endforeach
        </select>

        <input type="tel" id="{{ $ad }}" name="{{ $ad }}" inputmode="tel" required
               value="{{ old($yol, $mevcutYerel) }}" placeholder="{{ $ipucu }}"
               x-mask:dynamic="ulke === '+90' ? '999 999 99 99' : ''"
               class="min-w-0 flex-1 {{ $ortak }} {{ $cerceve }}">
    </div>

    @if ($hata)<p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>@endif
</div>
