{{-- Denetim kaydı · eski → yeni. Yorumlamadan, ham hâliyle.
     İçerik `denetim-ayrinti` modalıyla aynı; artık kalıcı adreste (S1). --}}
<x-filament::section>
    @if (! filled($kayit->eski) && ! filled($kayit->yeni))
        <p style="font-size:.85rem; opacity:.7;">Bu olay bir alan değişikliği taşımıyor.</p>
    @else
        <div style="display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit, minmax(18rem, 1fr));">
            @foreach (['Eski' => $kayit->eski, 'Yeni' => $kayit->yeni] as $baslik => $veri)
                <div>
                    <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.1em;
                                opacity:.55; margin-bottom:.35rem;">{{ $baslik }}</div>
                    <pre style="margin:0; padding:.7rem; border-radius:.5rem; font-size:.75rem;
                                background:rgba(127,127,127,.08); border:1px solid rgba(127,127,127,.2);
                                white-space:pre-wrap; word-break:break-word; max-height:22rem; overflow:auto;">{{ filled($veri) ? json_encode($veri, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                </div>
            @endforeach
        </div>
    @endif

    @if (filled($kayit->tarayici))
        <div style="margin-top:.9rem; font-size:.72rem; opacity:.55; word-break:break-all;">
            {{ $kayit->tarayici }}
        </div>
    @endif
</x-filament::section>
