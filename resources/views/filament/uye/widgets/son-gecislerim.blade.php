{{-- Son geçişlerim -- briefi md. B.1, Widget 5. Hiç geçiş yoksa widget
     canView() ile tamamen gizlenir. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Son geçişlerim</x-slot>

        <ul style="display:flex; flex-direction:column; gap:.7rem;">
            @foreach ($this->kayitlar as $gecis)
                <li style="display:flex; gap:.6rem; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <div style="font-size:.85rem; font-weight:600;">
                            {{ $gecis->kapiIstemcisi?->ad ?? $gecis->kapi_kodu ?? 'Kapı' }}
                        </div>
                        <div style="font-size:.75rem; opacity:.6;">
                            {{ $gecis->okundu_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                        </div>
                    </div>

                    {{-- 🔑 UYARI RET DEĞİL: kişi geçti, yalnızca görevli uyarıldı. --}}
                    <x-filament::badge :color="$gecis->sonuc->renk()">
                        {{ $gecis->sonuc->etiket() }}
                    </x-filament::badge>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
