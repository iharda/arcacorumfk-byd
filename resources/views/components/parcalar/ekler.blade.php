{{-- Ek listesi -- Saha notları S3.

     🪤 Eski hâlde AYNI LİSTEDE iki farklı görsel dil vardı: görsel ve videolar
     minmax(22rem, 1fr) ızgarasında TAM BOY basılıyor, PDF ve diğerleri 📎
     emojili düz bağlantı oluyordu. Altı ekli bir bülten sayfalarca uzuyordu.

     Yeni düzen: görseller 96 px'lik küçük resim şeridi, gerisi tek satır
     biçiminde (rozet · ad · boyut · önizle · indir). İkisi de aynı önizleyiciyi
     (S2) açıyor -- üye paneli ile yönetim paneli aynı bileşeni paylaşsın.

     Ek yoksa bu bileşen HİÇBİR ŞEY basmaz; başlık da çizilmez. --}}
@props([
    'ekler' => [],
    'baslik' => 'Ekler',
    // Yolu adrese çeviren kapanış; varsayılan akredite kullanıcıya açık rota.
    'adres' => null,
])

@php
    $ekler = array_values(array_filter((array) $ekler));
    $adres ??= fn (string $yol) => route('icerik.dosya', ['yol' => $yol]);

    $gorseller = array_values(array_filter($ekler, fn ($e) => \App\Support\DosyaTuru::gorselMi($e)));
    $digerleri = array_values(array_filter($ekler, fn ($e) => ! \App\Support\DosyaTuru::gorselMi($e)));
@endphp

@if ($ekler !== [])
    <div x-data="{ secili: null }" style="margin-top:1rem;">
        <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.6rem;">
            <span style="font-size:.8rem; font-weight:600;">{{ $baslik }}</span>
            {{-- Ek sayısı başlıkta: kaç dosya olduğu açmadan görünsün. --}}
            <x-filament::badge>{{ count($ekler) }}</x-filament::badge>
        </div>

        @if ($gorseller !== [])
            <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.6rem;">
                @foreach ($gorseller as $ek)
                    <button type="button" @click="secili = (secili === @js($ek) ? null : @js($ek))"
                            :style="'width:96px; height:96px; padding:0; cursor:pointer; overflow:hidden;'
                                + 'border-radius:.5rem; border:2px solid '
                                + (secili === @js($ek) ? 'rgba(127,127,127,.6)' : 'rgba(127,127,127,.2)')"
                            title="{{ \App\Support\DosyaTuru::ad($ek) }}">
                        <img src="{{ $adres($ek) }}" alt="{{ \App\Support\DosyaTuru::ad($ek) }}" loading="lazy"
                             style="width:100%; height:100%; object-fit:cover; display:block;">
                    </button>
                @endforeach
            </div>
        @endif

        @if ($digerleri !== [])
            <div style="display:flex; flex-direction:column; gap:.35rem;">
                @foreach ($digerleri as $ek)
                    <div style="display:flex; align-items:center; gap:.6rem; font-size:.82rem;
                                padding:.4rem .6rem; border:1px solid rgba(127,127,127,.2); border-radius:.5rem;">
                        <span style="font-family:ui-monospace, monospace; font-size:.7rem; font-weight:600;
                                     padding:.1rem .4rem; border-radius:.25rem; background:rgba(127,127,127,.15);">
                            {{ \App\Support\DosyaTuru::rozet($ek) }}
                        </span>
                        {{-- Uzun ad kırpılır, tamamı title'da durur. --}}
                        <span title="{{ \App\Support\DosyaTuru::ad($ek) }}"
                              style="flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis;
                                     white-space:nowrap;">{{ \App\Support\DosyaTuru::ad($ek) }}</span>
                        <button type="button" @click="secili = (secili === @js($ek) ? null : @js($ek))"
                                style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline;">
                            <span x-show="secili !== @js($ek)">Önizle</span>
                            <span x-show="secili === @js($ek)" x-cloak>Kapat</span>
                        </button>
                        <a href="{{ $adres($ek) }}" download>İndir</a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Tek önizleyici: hangi ek seçiliyse o açılır. --}}
        @foreach ($ekler as $ek)
            <div x-show="secili === @js($ek)" x-cloak style="margin-top:.75rem;">
                <x-parcalar.dosya-onizleme
                    :kaynak="$adres($ek)"
                    :mime="\App\Support\DosyaTuru::mime($ek)"
                    :ad="\App\Support\DosyaTuru::ad($ek)"
                    yukseklik="min(60vh, 700px)" />
            </div>
        @endforeach
    </div>
@endif
