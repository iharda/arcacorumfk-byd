{{-- Değerlendirme şeridi (1-5) -- Geliştirme briefi 28.08.2026, md. A.7.1.

     🪤 Panelde kendi Tailwind sınıflarımız DERLENMEZ (bkz. basvuru-inceleme
     .blade.php başındaki not). Renk ve yerleşim satır içi `style` ile yazılır;
     CSP buna izin veriyor (`style-src 'self' 'unsafe-inline'`). Yeni sınıf adı
     üretmeyin, sessizce çalışmaz.

     ♿ Renk TEK BAŞINA bilgi taşımaz: kutucukların yanında sayı ve etiket
     metni her zaman görünür, ayrıca title/aria-label verilir. --}}
@props([
    // App\Models\Degerlendirme|null
    'degerlendirme' => null,
    // Tabloda/dar alanda tek satır: kutucuklar küçülür, not gizlenir.
    'kompakt' => false,
    'baslik' => 'Değerlendirme',
])

@php
    $puan = $degerlendirme?->puan;
    $secili = $puan?->value ?? 0;
    $kutuBoy = $kompakt ? '.45rem' : '.7rem';
    $kutuEn = $kompakt ? '1.1rem' : '2.2rem';
    $ozet = $puan ? $puan->value.' · '.$puan->etiket() : 'Henüz değerlendirilmedi';
@endphp

<div {{ $attributes->merge(['style' => 'min-width:0;']) }}>
    @unless ($kompakt)
        <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">{{ $baslik }}</div>
    @endunless

    <div style="display:flex; align-items:center; gap:.5rem; margin-top:{{ $kompakt ? '0' : '.35rem' }};"
         role="img"
         aria-label="{{ $baslik }}: {{ $ozet }}"
         title="{{ $ozet }}">

        <div style="display:flex; gap:.15rem;" aria-hidden="true">
            @foreach (\App\Enums\DegerlendirmePuani::cases() as $kademe)
                @php $dolu = $kademe->value <= $secili; @endphp
                <span style="display:block;
                             width:{{ $kutuEn }}; height:{{ $kutuBoy }};
                             border-radius:.15rem;
                             background:{{ $dolu ? $kademe->hex() : 'currentColor' }};
                             opacity:{{ $dolu ? '1' : '.15' }};"></span>
            @endforeach
        </div>

        <span style="font-size:{{ $kompakt ? '.75rem' : '.82rem' }}; white-space:nowrap; {{ $puan ? '' : 'opacity:.55;' }}">
            {{ $ozet }}
        </span>
    </div>

    @if (! $kompakt && $degerlendirme)
        @if (filled($degerlendirme->not))
            <p style="margin-top:.5rem; font-size:.8rem; white-space:pre-line;">“{{ $degerlendirme->not }}”</p>
        @endif

        <div style="margin-top:.35rem; font-size:.72rem; opacity:.55;">
            {{ $degerlendirme->degerlendiren_ad ?? 'Bilinmeyen' }}
            · {{ $degerlendirme->updated_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
        </div>
    @endif
</div>
