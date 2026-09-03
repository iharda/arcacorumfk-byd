{{-- Eksik kurum bilgisi -- briefi md. B.2, Widget 4.
     Eksik yoksa widget hiç render edilmez (canView()). --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kurum bilgileriniz eksik</x-slot>
        <x-slot name="description">
            Eksik bilgi akreditasyon yenilemesinde sorun çıkarır. Tamamlanması için kulüple iletişime geçin.
        </x-slot>

        <ul style="display:flex; flex-wrap:wrap; gap:.4rem;">
            @foreach ($this->eksikler as $eksik)
                <li><x-filament::badge color="warning">{{ $eksik }}</x-filament::badge></li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
