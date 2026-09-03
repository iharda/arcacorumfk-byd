{{-- Dikkat gerektirenler -- briefi md. B.3, Widget D.
     Satır yoksa widget hiç render edilmez (canView()). --}}
@php
    $renkler = ['danger' => '#dc2626', 'warning' => '#d97706'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Dikkat gerektirenler</x-slot>
        <x-slot name="description">
            Sebep başına en fazla beş satır gösterilir.
        </x-slot>

        {{-- ⚠️ Dar ekranda tablo taşmasın: kendi kaydırma kabında. --}}
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:.83rem; min-width:34rem;">
                <thead>
                    <tr style="text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">
                        <th style="padding:.4rem .5rem;">Sebep</th>
                        <th style="padding:.4rem .5rem;">Kayıt</th>
                        <th style="padding:.4rem .5rem;">Ayrıntı</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->satirlar as $satir)
                        <tr style="border-top:1px solid rgba(127,127,127,.18);">
                            <td style="padding:.5rem;">
                                <x-filament::badge :color="$satir['renk']" size="sm">{{ $satir['sebep'] }}</x-filament::badge>
                            </td>
                            <td style="padding:.5rem; font-weight:600;">
                                <a href="{{ $satir['adres'] }}" style="text-decoration:underline;">{{ $satir['baslik'] }}</a>
                            </td>
                            <td style="padding:.5rem; opacity:.75;">{{ $satir['ayrinti'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
