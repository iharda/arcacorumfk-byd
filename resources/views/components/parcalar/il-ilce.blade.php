{{-- Bağlı il / ilçe seçimi -- Revizyon md.5.1.

     İl listesi SUNUCUDA basılır (JS kapalıyken de çalışır), ilçeler istemcide
     süzülür. 81 il + 973 ilçe ~11 KB: ek istek yerine sayfaya gömmek daha ucuz.

     🪤 İstemci seçimi bağlayıcı DEĞİL: ilçenin gerçekten o ile ait olduğu
     sunucuda `IlIlce::gecerliMi()` ile ayrıca doğrulanır. --}}
@props(['ilAd' => 'il', 'ilceAd' => 'ilce'])

@php
    $ilHata = $errors->first($ilAd);
    $ilceHata = $errors->first($ilceAd);
    $kutuSinifi = fn (bool $hata) => implode(' ', [
        'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition',
        'focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
        'disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-400',
        $hata ? 'border-kulup-600 bg-kulup-50' : 'border-neutral-300 bg-white',
    ]);
@endphp

{{-- `contents`: sarmalayıcı ızgara hücresi yemesin, iki alan yan yana kalsın. --}}
<div class="contents" x-data="{
        ilceler: @js(App\Support\IlIlce::tumu()),
        il: @js(old($ilAd, '')),
        ilce: @js(old($ilceAd, '')),
     }">
    <div>
        <label for="{{ $ilAd }}" class="zorunlu block text-sm font-medium text-neutral-800">İl</label>
        <select id="{{ $ilAd }}" name="{{ $ilAd }}" required
                x-model="il" @change="ilce = ''"
                class="{{ $kutuSinifi((bool) $ilHata) }}">
            <option value="">Seçiniz…</option>
            @foreach (App\Support\IlIlce::iller() as $ad)
                <option value="{{ $ad }}" @selected(old($ilAd) === $ad)>{{ $ad }}</option>
            @endforeach
        </select>
        @if ($ilHata)<p class="mt-1 text-xs text-kulup-700">{{ $ilHata }}</p>@endif
    </div>

    <div>
        <label for="{{ $ilceAd }}" class="zorunlu block text-sm font-medium text-neutral-800">İlçe</label>
        <select id="{{ $ilceAd }}" name="{{ $ilceAd }}" required
                x-model="ilce" :disabled="! il"
                class="{{ $kutuSinifi((bool) $ilceHata) }}">
            <option value="" x-text="il ? 'Seçiniz…' : 'Önce il seçin'">Önce il seçin</option>
            <template x-for="ad in (ilceler[il] ?? [])" :key="ad">
                <option :value="ad" x-text="ad"></option>
            </template>
        </select>
        @if ($ilceHata)<p class="mt-1 text-xs text-kulup-700">{{ $ilceHata }}</p>@endif
    </div>
</div>
