{{-- Geçiş kaydı detayı · aynı karta ait diğer okutmalar (S1).
     Reddedilen bir geçişin sebebi çoğu zaman komşu satırlarda görünüyor. --}}
<x-filament::section>
    @if (! $kartVar)
        <p style="font-size:.85rem; opacity:.7;">
            Bu okutma tanınan bir karta bağlı değil; karşılaştırılacak başka kayıt yok.
        </p>
    @elseif ($okutmalar->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">Bu kartın başka okutması yok.</p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($okutmalar as $g)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.85rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <a href="{{ \App\Filament\Yonetim\Resources\GecisKayitlari\GecisKaydiResource::getUrl('detay', ['record' => $g]) }}"
                       style="opacity:.75; font-variant-numeric:tabular-nums; white-space:nowrap;">
                        {{ $g->okundu_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i:s') }}
                    </a>
                    <x-filament::badge :color="$g->sonuc->renk()">{{ $g->sonuc->etiket() }}</x-filament::badge>
                    <span>{{ $g->kapiIstemcisi?->ad ?? $g->kapi_kodu ?? '—' }}</span>
                    <span style="opacity:.65;">{{ $g->yon === 'giris' ? 'Giriş' : 'Çıkış' }}</span>
                    @if (filled($g->sebep))
                        <span style="flex:1 1 100%; opacity:.7;">{{ $g->sebep }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
