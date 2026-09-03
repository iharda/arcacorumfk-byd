{{-- Son içerikler (duyuru / bülten / antrenman) -- briefi md. B.2, Widget 5.
     Kayıt yoksa widget hiç render edilmez (canView()). --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Son içerikler</x-slot>

        <ul style="display:flex; flex-direction:column; gap:.75rem;">
            @foreach ($this->satirlar as $satir)
                <li>
                    <a href="{{ $satir['adres'] }}" style="display:block; text-decoration:none; min-width:0;">
                        <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                            <x-filament::badge :color="$satir['renk']" size="sm">{{ $satir['etiket'] }}</x-filament::badge>
                            <span style="font-size:.86rem; font-weight:600; word-break:break-word;">{{ $satir['baslik'] }}</span>
                        </div>
                        <div style="margin-top:.15rem; font-size:.72rem; opacity:.55;">
                            {{ $satir['tarih']?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
