{{-- İki seçenekli radyo. Varsayılan SEÇİLİ DEĞİL: kullanıcı bilerek işaretlesin,
     "hayır" sessizce varsayılmasın. --}}
@props(['ad', 'etiket', 'zorunlu' => false])

@php $hata = $errors->first($ad); @endphp

<div>
    <span @class(['block text-sm font-medium text-neutral-800', 'zorunlu' => $zorunlu])>{{ $etiket }}</span>
    <div class="mt-2 flex gap-4">
        @foreach ([1 => 'Var', 0 => 'Yok'] as $deger => $metin)
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="{{ $ad }}" value="{{ $deger }}"
                       @checked((string) old($ad) === (string) $deger)
                       @if($zorunlu) required @endif
                       class="h-4 w-4 border-neutral-300 text-kulup-600 focus:ring-kulup-600/30">
                <span>{{ $metin }}</span>
            </label>
        @endforeach
    </div>
    @if($hata)<p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>@endif
</div>
