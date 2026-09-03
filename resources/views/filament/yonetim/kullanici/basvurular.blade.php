{{-- Kullanıcı detayı · Başvuruları sekmesi (T13). --}}
<x-filament::section>
    @if ($basvurular->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">Bu kullanıcıya ait başvuru yok.</p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($basvurular as $b)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.88rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <a href="{{ \App\Filament\Yonetim\Resources\Basvurus\BasvuruResource::getUrl('inceleme', ['record' => $b]) }}"
                       style="font-weight:500;">{{ $b->basvuru_no ?? '—' }}</a>
                    <span>{{ $b->tur->etiket() }}</span>
                    <span style="opacity:.65; font-variant-numeric:tabular-nums;">
                        {{ $b->gonderildi_at?->timezone('Europe/Istanbul')?->format('d.m.Y') ?? '—' }}
                    </span>
                    <span style="margin-left:auto;">
                        <x-filament::badge :color="$b->durum->renk()">{{ $b->durum->etiket() }}</x-filament::badge>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
