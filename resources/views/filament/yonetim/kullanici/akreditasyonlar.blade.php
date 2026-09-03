{{-- Kullanıcı detayı · Akreditasyonları sekmesi (T13). --}}
<x-filament::section>
    @if ($akreditasyonlar->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">Bu kullanıcının akreditasyonu yok.</p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($akreditasyonlar as $a)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.88rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <a href="{{ \App\Filament\Yonetim\Resources\Akreditasyonlar\AkreditasyonResource::getUrl('detay', ['record' => $a]) }}"
                       style="font-weight:500;">{{ $a->kart_no }}</a>
                    <span style="opacity:.65;">{{ $a->kurum?->resmi_unvan ?? 'Bağımsız' }}</span>
                    <span style="margin-left:auto;">
                        <x-filament::badge :color="$a->durum->renk()">{{ $a->durum->etiket() }}</x-filament::badge>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
