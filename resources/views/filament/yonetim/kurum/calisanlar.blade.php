{{-- Kurum detayı · Çalışanlar sekmesi (T5). --}}
<x-filament::section>
    @if ($calisanlar->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">
            Bu kuruma bağlı çalışan yok — kurum yetkilisi davet gönderdiğinde buraya düşer.
        </p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($calisanlar as $c)
                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.88rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <span style="font-weight:500;">{{ $c->name }}</span>
                    <span style="opacity:.65;">{{ $c->email }}</span>
                    <span style="margin-left:auto;">
                        <x-filament::badge :color="$c->aktif ? 'success' : 'gray'">
                            {{ $c->aktif ? 'Aktif' : 'Pasif' }}
                        </x-filament::badge>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
