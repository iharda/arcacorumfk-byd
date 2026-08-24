{{-- Ülke kodu + maskeli telefon -- Revizyon md.5.2.

     Saklama biçimi E.164 (`+905321234567`); maske yalnızca yazarken yardımcı.
     🪤 Maske TÜRKİYE'ye özel: ülke değişince kaldırılır, yoksa yabancı numarayı
     10 haneye kırpardı. --}}
@props([
    'ad',
    'etiket' => 'Telefon',
    'ipucu' => '532 123 45 67',
    'sutun' => 1,
])

@php
    $ulkeAd = $ad.'_ulke';
    $hata = $errors->first($ad) ?: $errors->first($ulkeAd);
    $ortak = 'rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none';
    $cerceve = $hata ? 'border-kulup-600 bg-kulup-50' : 'border-neutral-300 bg-white';
@endphp

<div @class(['sm:col-span-2' => $sutun === 2])
     x-data="{ ulke: @js(old($ulkeAd, App\Support\UlkeKodu::VARSAYILAN)) }">
    <label for="{{ $ad }}" class="zorunlu block text-sm font-medium text-neutral-800">{{ $etiket }}</label>

    <div class="mt-1.5 flex gap-2">
        <select name="{{ $ulkeAd }}" x-model="ulke" aria-label="{{ $etiket }} ülke kodu"
                class="w-28 shrink-0 {{ $ortak }} {{ $cerceve }}">
            @foreach (App\Support\UlkeKodu::hepsi() as $kod => $kodEtiketi)
                <option value="{{ $kod }}" @selected(old($ulkeAd, App\Support\UlkeKodu::VARSAYILAN) === $kod)>
                    {{ $kodEtiketi }}
                </option>
            @endforeach
        </select>

        <input type="tel" id="{{ $ad }}" name="{{ $ad }}" inputmode="tel" required
               value="{{ old($ad) }}" placeholder="{{ $ipucu }}"
               x-mask:dynamic="ulke === '+90' ? '999 999 99 99' : ''"
               class="min-w-0 flex-1 {{ $ortak }} {{ $cerceve }}">
    </div>

    @if ($hata)<p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>@endif
</div>
