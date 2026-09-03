{{-- Yapılacaklar -- briefi md. B.1, Widget 2. Hepsi temizse widget hiç
     render edilmez (canView()); bu yüzden burada boş durum yoktur.
     ⚠️ Renkler satır içi: panelde kendi Tailwind sınıflarımız derlenmiyor. --}}
@php
    $renkler = ['danger' => '#dc2626', 'warning' => '#d97706', 'gray' => 'currentColor'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Yapılacaklar</x-slot>

        <ul style="display:flex; flex-direction:column; gap:.7rem;">
            @foreach ($this->isler as $is)
                <li>
                    <a href="{{ $is['adres'] }}"
                       style="display:flex; gap:.6rem; align-items:flex-start; text-decoration:none;
                              color:{{ $renkler[$is['renk']] ?? 'currentColor' }};">
                        <x-filament::icon :icon="$is['ikon']"
                                          style="width:1.15rem; height:1.15rem; flex:0 0 auto; margin-top:.1rem;" />
                        <span style="font-size:.85rem; {{ $is['renk'] === 'gray' ? 'opacity:.85;' : 'font-weight:600;' }}">
                            {{ $is['metin'] }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
