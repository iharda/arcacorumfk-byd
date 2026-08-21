{{-- Basına açık antrenman takvimi. --}}
<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">Yaklaşan antrenmanlar</x-slot>

        @if ($this->yaklasanlar->isEmpty())
            <p style="font-size:.9rem; opacity:.65;">Takvimde yaklaşan bir antrenman görünmüyor.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:.6rem;">
                @foreach ($this->yaklasanlar as $a)
                    <div style="display:flex; flex-wrap:wrap; gap:.9rem; align-items:flex-start;
                                padding:.9rem 1rem; border:1px solid rgb(var(--gray-200)); border-radius:.6rem;">
                        {{-- Tarih bloğu: listede gözle taranabilsin --}}
                        <div style="flex:none; text-align:center; min-width:3.4rem;">
                            <div style="font-size:1.35rem; font-weight:700; line-height:1;">
                                {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('d') }}
                            </div>
                            <div style="font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; opacity:.6;">
                                {{ $a->baslangic_at->timezone('Europe/Istanbul')->translatedFormat('M') }}
                            </div>
                        </div>

                        <div style="flex:1 1 14rem; min-width:11rem;">
                            <div style="font-size:.92rem; font-weight:600;">{{ $a->baslik ?: 'Antrenman' }}</div>
                            <div style="font-size:.8rem; opacity:.7; margin-top:.15rem;">
                                {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('H:i') }}
                                @if ($a->bitis_at) – {{ $a->bitis_at->timezone('Europe/Istanbul')->format('H:i') }} @endif
                                @if ($a->yer) · {{ $a->yer }} @endif
                            </div>
                            @if (filled($a->not))
                                <div style="font-size:.8rem; opacity:.8; margin-top:.4rem;">{{ $a->not }}</div>
                            @endif
                        </div>

                        <x-filament::badge :color="$a->basina_acik ? 'success' : 'gray'">
                            {{ $a->basina_acik ? 'Basına açık' : 'Basına kapalı' }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @if ($this->gecmis->isNotEmpty())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Geçmiş antrenmanlar</x-slot>
            <div style="display:flex; flex-direction:column; gap:.4rem;">
                @foreach ($this->gecmis as $a)
                    <div style="display:flex; gap:.75rem; font-size:.85rem; opacity:.75;">
                        <span style="flex:none; min-width:8.5rem;">
                            {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                        </span>
                        <span>{{ $a->baslik ?: 'Antrenman' }}@if ($a->yer) · {{ $a->yer }} @endif</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
