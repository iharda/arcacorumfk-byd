{{-- İki seçenekli radyo. Varsayılan SEÇİLİ DEĞİL: kullanıcı bilerek işaretlesin,
     "hayır" sessizce varsayılmasın.

     🔤 "Var / Yok" DEĞİL "Evet / Hayır" (Cüneyt Bey revizyonu 03.09.2026):
     etiketler artık tam soru cümlesi ("Geçerli bir basın kartınız var mı?"),
     cevap da soruya uymalı. --}}
@props(['ad', 'etiket', 'zorunlu' => false, 'deger' => null, 'yol' => null])

{{-- 🪤 `yol`: `old()` ve `$errors` NOKTA yolu ister. Girdi adı
     `alan[veri_telefon]` gibi köşeli parantezliyse `old('alan[veri_telefon]')`
     hiçbir zaman eşleşmez ve doğrulama hatasından sonra kutu BOŞALIR. --}}
@php $yol = $yol ?? $ad; @endphp

@php $hata = $errors->first($yol); @endphp

<div>
    <span @class(['block text-sm font-medium text-neutral-800', 'zorunlu' => $zorunlu])>{{ $etiket }}</span>
    <div class="mt-2 flex gap-4">
        @foreach ([1 => 'Evet', 0 => 'Hayır'] as $secenek => $metin)
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="{{ $ad }}" value="{{ $secenek }}"
                       @checked((string) old($yol, $deger === null ? '' : (int) $deger) === (string) $secenek)
                       @if($zorunlu) required @endif
                       class="h-4 w-4 border-neutral-300 text-kulup-600 focus:ring-kulup-600/30">
                <span>{{ $metin }}</span>
            </label>
        @endforeach
    </div>
    @if($hata)<p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>@endif
</div>
