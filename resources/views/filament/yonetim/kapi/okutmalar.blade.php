{{-- Kapı istemcisi detayı · bu kapıdan geçen son okutmalar (S1). --}}
<x-filament::section>
    @if ($okutmalar->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">
            Henüz okutma yok — bu turnikede ilk kart okutulduğunda buraya düşer.
        </p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($okutmalar as $g)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.85rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <span style="opacity:.55; font-variant-numeric:tabular-nums; white-space:nowrap;">
                        {{ $g->okundu_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s') }}
                    </span>
                    <x-filament::badge :color="$g->sonuc->renk()">{{ $g->sonuc->etiket() }}</x-filament::badge>
                    <span>{{ $g->akreditasyon?->kart_no ?? $g->okunan_referans ?? '—' }}</span>
                    <span style="opacity:.65;">{{ $g->akreditasyon?->kullanici?->name ?? '' }}</span>
                    @if (filled($g->sebep))
                        <span style="flex:1 1 100%; opacity:.7;">{{ $g->sebep }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
