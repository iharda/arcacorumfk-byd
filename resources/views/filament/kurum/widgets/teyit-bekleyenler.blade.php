{{-- Teyit bekleyenler -- briefi md. B.2, Widget 2.
     Kayıt yoksa widget hiç render edilmez (canView()). --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Teyidinizi bekleyenler</x-slot>
        <x-slot name="description">
            Teyit vermeden başvuru kulüp incelemesine geçmez.
        </x-slot>

        <ul style="display:flex; flex-direction:column; gap:.75rem;">
            @foreach ($this->bekleyenler as $basvuru)
                @php
                    $gonderim = $basvuru->gonderildi_at?->timezone('Europe/Istanbul');
                    $gun = $gonderim ? (int) $gonderim->copy()->startOfDay()->diffInDays(now('Europe/Istanbul')->startOfDay()) : 0;
                    $gecikti = $gun > 3;
                @endphp

                <li style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; justify-content:space-between;
                           padding:.6rem .7rem; border-radius:.5rem;
                           {{ $gecikti ? 'background:rgba(220,38,38,.08); border:1px solid rgba(220,38,38,.35);' : 'background:rgba(127,127,127,.07);' }}">

                    <div style="min-width:0;">
                        <div style="font-size:.87rem; font-weight:600; word-break:break-word;">
                            {{ $basvuru->basvuranAdi() }}
                        </div>
                        <div style="font-size:.75rem; opacity:.7; margin-top:.1rem;">
                            {{ $basvuru->tur->etiket() }} · {{ $basvuru->basvuru_no ?? '—' }}
                            @if ($gonderim)
                                · {{ $gonderim->format('d.m.Y') }}
                                <span style="{{ $gecikti ? 'font-weight:600;' : '' }}">
                                    ({{ $gun === 0 ? 'bugün' : $gun.' gündür bekliyor' }})
                                </span>
                            @endif
                        </div>
                    </div>

                    <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                        {{ ($this->teyitAction)(['basvuru' => $basvuru->ulid]) }}
                        {{ ($this->teyitReddetAction)(['basvuru' => $basvuru->ulid]) }}
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($this->kalan > 0)
            {{-- Sessiz kırpma yok: kaç satırın gösterilmediği yazar. --}}
            <div style="margin-top:.8rem; font-size:.8rem;">
                <x-filament::link :href="route('filament.kurum.pages.calisanlar')">
                    {{ $this->kalan }} kişi daha bekliyor — tümünü gör
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
