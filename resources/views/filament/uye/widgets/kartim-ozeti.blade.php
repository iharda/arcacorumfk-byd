{{-- "Kartım geçerli mi?" -- briefi md. B.1, Widget 1.
     ⚠️ Panelde kendi Tailwind sınıflarımız derlenmez; yerleşim satır içi stille. --}}
@php
    $a = $this->akreditasyon;
    $uyari = $this->uyari;
    $kalan = $this->kalanGun;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kartım</x-slot>

        @if ($uyari)
            {{-- Renk tek başına bilgi taşımasın: metin de burada. --}}
            <div style="margin-bottom:1rem;">
                <x-filament::badge :color="$uyari['renk']">{{ $uyari['metin'] }}</x-filament::badge>
            </div>
        @endif

        <div style="display:flex; flex-wrap:wrap; gap:1.25rem; align-items:flex-start;">

            @if ($this->gorselAdresi)
                <a href="{{ route('filament.uye.pages.kartim') }}" style="flex:0 0 auto;">
                    <img src="{{ $this->gorselAdresi }}" alt="Basın kartı"
                         style="width:200px; max-width:100%; height:auto; display:block;
                                border-radius:.6rem; box-shadow:0 4px 16px rgb(0 0 0 / .12);">
                </a>
            @endif

            <div style="flex:1 1 16rem; min-width:0;">
                @if ($a)
                    <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;">
                        <x-filament::badge :color="$a->durum->renk()" size="lg">{{ $a->durum->etiket() }}</x-filament::badge>
                        <span style="font-weight:600;">{{ $a->kart_no }}</span>
                    </div>

                    <dl style="margin-top:.9rem; display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                        @if ($a->kurum)
                            <dt style="opacity:.6; white-space:nowrap;">Kurum</dt>
                            <dd style="word-break:break-word;">{{ $a->kurum->resmi_unvan }}</dd>
                        @endif

                        @if ($a->gecerlilik_bitis)
                            <dt style="opacity:.6; white-space:nowrap;">Geçerlilik</dt>
                            <dd>
                                {{ $a->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y') }}
                                @if ($kalan !== null)
                                    <span style="opacity:.6;">
                                        · {{ $kalan >= 0 ? $kalan.' gün kaldı' : 'süresi doldu' }}
                                    </span>
                                @endif
                            </dd>
                        @endif
                    </dl>
                @else
                    <x-filament::badge color="gray" size="lg">Akreditasyon yok</x-filament::badge>
                @endif

                @if ($this->durumMesaji)
                    <p style="margin-top:.9rem; font-size:.85rem; opacity:.8;">{{ $this->durumMesaji }}</p>
                @endif

                @if ($a)
                    <div style="margin-top:1rem;">
                        <x-filament::link :href="route('filament.uye.pages.kartim')">Kartıma git</x-filament::link>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
