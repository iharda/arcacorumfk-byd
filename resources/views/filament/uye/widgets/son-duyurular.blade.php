{{-- Son duyurular -- briefi md. B.1, Widget 4. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Son duyurular</x-slot>
        <x-slot name="headerEnd">
            <x-filament::link :href="route('filament.uye.pages.duyurular')" size="sm">Tümü</x-filament::link>
        </x-slot>

        @if ($this->kayitlar->isEmpty())
            <p style="font-size:.85rem; opacity:.65;">Yayımlanmış duyuru yok.</p>
        @else
            <ul style="display:flex; flex-direction:column; gap:.85rem;">
                @foreach ($this->kayitlar as $duyuru)
                    <li>
                        <a href="{{ route('filament.uye.pages.duyurular') }}"
                           style="display:block; text-decoration:none; min-width:0;">
                            <div style="display:flex; gap:.4rem; align-items:center;">
                                @if ($duyuru->video_yolu)
                                    <x-filament::icon icon="heroicon-m-play-circle" style="width:1rem; height:1rem; flex:0 0 auto; opacity:.6;" />
                                @elseif ($duyuru->gorsel_yolu)
                                    <x-filament::icon icon="heroicon-m-photo" style="width:1rem; height:1rem; flex:0 0 auto; opacity:.6;" />
                                @endif
                                <span style="font-size:.87rem; font-weight:600; word-break:break-word;">{{ $duyuru->baslik }}</span>
                            </div>

                            @if (filled($duyuru->ozet))
                                {{-- İki satırda kırp: kutu uzunluğu duyuru metnine göre oynamasın. --}}
                                <p style="margin-top:.2rem; font-size:.79rem; opacity:.72; overflow:hidden;
                                          display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                    {{ $duyuru->ozet }}
                                </p>
                            @endif

                            <div style="margin-top:.2rem; font-size:.72rem; opacity:.55;">
                                {{ ($duyuru->yayin_at ?? $duyuru->created_at)?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($this->sonBulten)
            <div style="margin-top:1rem; padding-top:.75rem; border-top:1px solid rgba(127,127,127,.2); font-size:.8rem;">
                Son bülten:
                <a href="{{ route('filament.uye.pages.bultenler') }}" style="text-decoration:underline;">
                    {{ $this->sonBulten->baslik }}
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
