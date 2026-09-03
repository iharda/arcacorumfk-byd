{{-- Yaklaşan antrenmanlar -- briefi md. B.1, Widget 3. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Yaklaşan antrenmanlar</x-slot>
        <x-slot name="headerEnd">
            <x-filament::link :href="route('filament.uye.pages.takvim')" size="sm">Takvim</x-filament::link>
        </x-slot>

        @if ($this->kayitlar->isEmpty())
            <p style="font-size:.85rem; opacity:.65;">Yayımlanmış yaklaşan antrenman yok.</p>
        @else
            <ul style="display:flex; flex-direction:column; gap:.85rem;">
                @foreach ($this->kayitlar as $antrenman)
                    @php $bas = $antrenman->baslangic_at->timezone('Europe/Istanbul'); @endphp
                    <li style="display:flex; gap:.75rem; align-items:flex-start;">
                        {{-- Gün/ay kutucuğu: tarih tek bakışta okunsun. --}}
                        <div style="flex:0 0 auto; text-align:center; min-width:2.6rem; padding:.35rem .4rem;
                                    border-radius:.4rem; background:rgba(127,127,127,.12);">
                            <div style="font-size:1.05rem; font-weight:700; line-height:1;">{{ $bas->format('d') }}</div>
                            <div style="font-size:.65rem; text-transform:uppercase; opacity:.65;">{{ $bas->translatedFormat('M') }}</div>
                        </div>

                        <div style="min-width:0;">
                            <div style="font-size:.87rem; font-weight:600; word-break:break-word;">
                                {{ $antrenman->baslik ?? 'Antrenman' }}
                            </div>
                            <div style="font-size:.78rem; opacity:.7; margin-top:.15rem;">
                                {{ $bas->format('H:i') }}@if (filled($antrenman->yer)) · {{ $antrenman->yer }} @endif
                            </div>
                            <div style="margin-top:.3rem;">
                                <x-filament::badge color="success" size="sm">Basına açık</x-filament::badge>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
