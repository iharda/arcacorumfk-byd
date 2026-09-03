{{-- Çalışan kart durumları -- briefi md. B.2, Widget 3.
     Dikkat gereken satır (kartı yok / askıda) ÜSTTE. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Çalışanlarım</x-slot>
        <x-slot name="headerEnd">
            <x-filament::link :href="route('filament.kurum.pages.calisanlar')" size="sm">Tümünü gör</x-filament::link>
        </x-slot>

        <ul style="display:flex; flex-direction:column; gap:.7rem;">
            @foreach ($this->calisanlar as $calisan)
                @php $a = $calisan->akreditasyon; @endphp
                <li style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; justify-content:space-between;">
                    <div style="min-width:0;">
                        <div style="font-size:.86rem; font-weight:600; word-break:break-word;">{{ $calisan->name }}</div>
                        <div style="font-size:.74rem; opacity:.65;">
                            @if ($a)
                                {{ $a->kart_no }}
                                @if ($a->gecerlilik_bitis)
                                    · {{ $a->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y') }}'e kadar
                                @endif
                            @else
                                Kart yok
                            @endif
                        </div>
                    </div>

                    <x-filament::badge :color="$a?->durum->renk() ?? 'gray'">
                        {{ $a?->durum->etiket() ?? 'Akreditasyon yok' }}
                    </x-filament::badge>
                </li>
            @endforeach
        </ul>

        @if ($this->toplam > $this->calisanlar->count())
            <div style="margin-top:.8rem; font-size:.78rem; opacity:.65;">
                {{ $this->toplam }} çalışandan ilk {{ $this->calisanlar->count() }} tanesi gösteriliyor.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
