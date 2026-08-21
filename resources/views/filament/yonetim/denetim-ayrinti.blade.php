{{-- Denetim kaydı ayrıntısı: eski → yeni. Yorumlamadan, ham hâliyle. --}}
<div style="display:flex; flex-direction:column; gap:1rem;">

    <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .9rem; font-size:.85rem;">
        @foreach ([
            'Zaman'  => $kayit->created_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i:s'),
            'Olay'   => $kayit->olay,
            'Kim'    => $kayit->aktor_ad ?: ucfirst($kayit->aktor_tip),
            'Kayıt'  => $kayit->kayit_etiketi,
            'Tip'    => $kayit->kayit_tipi ? class_basename($kayit->kayit_tipi) : null,
            'IP'     => $kayit->ip,
            'Not'    => $kayit->not,
        ] as $etiket => $deger)
            @if (filled($deger))
                <dt style="opacity:.6; white-space:nowrap;">{{ $etiket }}</dt>
                <dd style="word-break:break-word;">{{ $deger }}</dd>
            @endif
        @endforeach
    </dl>

    @if (filled($kayit->eski) || filled($kayit->yeni))
        <div style="display:grid; gap:.75rem; grid-template-columns:1fr 1fr;">
            @foreach (['Eski' => $kayit->eski, 'Yeni' => $kayit->yeni] as $baslik => $veri)
                <div>
                    <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.1em; opacity:.55; margin-bottom:.35rem;">
                        {{ $baslik }}
                    </div>
                    <pre style="margin:0; padding:.7rem; border-radius:.5rem; font-size:.75rem;
                                background:rgb(var(--gray-50)); border:1px solid rgb(var(--gray-200));
                                white-space:pre-wrap; word-break:break-word; max-height:18rem; overflow:auto;">{{ filled($veri) ? json_encode($veri, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                </div>
            @endforeach
        </div>
    @endif

    @if (filled($kayit->tarayici))
        <div style="font-size:.72rem; opacity:.55; word-break:break-all;">{{ $kayit->tarayici }}</div>
    @endif

</div>
