{{-- Tek form alanı: etiket + girdi + hata. Hata bildirimi kalır, gereksiz
     açıklama paragrafı KONMAZ (kalıcı arayüz kuralı). --}}
@props([
    'ad',
    'etiket',
    'tur' => 'text',
    'zorunlu' => false,
    'deger' => null,
    'ipucu' => null,
    'sutun' => 2,
    'yol' => null,
])

@php $yol = $yol ?? $ad; @endphp

{{-- 🪤 `yol`: `old()` ve `$errors` NOKTA yolu ister. Girdi adı
     `alan[veri_telefon]` gibi köşeli parantezliyse `old('alan[veri_telefon]')`
     hiçbir zaman eşleşmez ve doğrulama hatasından sonra kutu BOŞALIR. --}}
@php $hata = $errors->first($yol); @endphp

<div @class(['sm:col-span-2' => $sutun === 2])>
    <label for="{{ $yol }}" @class(['block text-sm font-medium text-neutral-800', 'zorunlu' => $zorunlu])>
        {{ $etiket }}
    </label>
    <input
        type="{{ $tur }}"
        id="{{ $yol }}"
        name="{{ $ad }}"
        value="{{ old($yol, $deger) }}"
        @if($zorunlu) required @endif
        @if($ipucu) placeholder="{{ $ipucu }}" @endif
        {{ $attributes->class([
            'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition',
            'focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
            'border-neutral-300 bg-white' => ! $hata,
            'border-kulup-600 bg-kulup-50' => (bool) $hata,
        ]) }}
    >
    @if($hata)
        <p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>
    @endif
</div>
