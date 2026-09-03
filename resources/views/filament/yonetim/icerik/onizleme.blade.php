{{-- Duyuru/bülten · üyenin göreceği hâlin önizlemesi (S1).
     Ek yerleşimi üye paneliyle AYNI bileşenden (S3): biri düzeltilip diğeri
     unutulmasın. --}}
<x-filament::section>
    <x-slot name="heading">{{ $baslik }}</x-slot>
    @if ($yayinAt)
        <x-slot name="description">
            {{ $yayinAt->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
        </x-slot>
    @endif

    @if (filled($ozet))
        <p style="font-size:.9rem; opacity:.75; margin:0 0 .75rem;">{{ $ozet }}</p>
    @endif

    @if (filled($icerik))
        <div class="fi-prose" style="font-size:.9rem; line-height:1.6;">
            {!! $icerik !!}
        </div>
    @else
        <p style="font-size:.85rem; opacity:.6;">İçerik girilmemiş.</p>
    @endif

    <x-parcalar.ekler :ekler="$ekler" />
</x-filament::section>
