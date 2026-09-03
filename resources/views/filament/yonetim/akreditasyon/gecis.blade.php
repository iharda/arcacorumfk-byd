{{-- Geçiş kayıtları sekmesi -- son 20 okutma, sonuçlarıyla.
     "Bu kişi maç günü kaç kez okuttu, nerede takıldı" sorusu burada kapanıyor;
     ayrı ekrana gitmek gerekmiyor. --}}
@if ($gecisler->isEmpty())
    <x-filament::section>
        <p style="font-size:.85rem; opacity:.7;">
            Henüz geçiş kaydı yok — turnikelerde ilk okutma yapıldığında buraya düşer.
        </p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">Son {{ $gecisler->count() }} okutma</x-slot>

        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($gecisler as $g)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.85rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <span style="opacity:.55; font-variant-numeric:tabular-nums; white-space:nowrap;">
                        {{ $g->okundu_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s') }}
                    </span>
                    <x-filament::badge :color="$g->sonuc->renk()">{{ $g->sonuc->etiket() }}</x-filament::badge>
                    <span>{{ $g->kapiIstemcisi?->ad ?? $g->kapi_kodu ?? '—' }}</span>
                    <span style="opacity:.65;">{{ $g->yon === 'giris' ? 'Giriş' : 'Çıkış' }}</span>
                    @if (filled($g->bolge))
                        <span style="opacity:.65;">{{ $g->bolge }}</span>
                    @endif
                    @if (filled($g->sebep))
                        <span style="flex:1 1 100%; opacity:.7;">{{ $g->sebep }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
@endif
