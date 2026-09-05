{{-- Ortak detay iskeleti -- S1. Dokuz sayfa bunu kullanacak; tek tasarım.
     Alanlar sayfa sınıfından gelir (kimlik/kunye/sekmeler), buraya sayfaya
     özel hiçbir şey yazılmaz. --}}
<x-filament-panels::page>

    {{-- ── 1) Başlık şeridi ─────────────────────────────────────── --}}
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem 1rem;">
        <div style="min-width:0;">
            <div style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;">
                <span style="font-size:1.35rem; font-weight:700; letter-spacing:-.01em;">{{ $this->kimlik() }}</span>
                @if ($rozet = $this->durumRozeti())
                    <x-filament::badge :color="$rozet['renk'] ?? 'gray'">{{ $rozet['etiket'] }}</x-filament::badge>
                @endif
            </div>
            @if ($alt = $this->altBaslik())
                <p style="margin:.2rem 0 0; font-size:.85rem; opacity:.65;">{{ $alt }}</p>
            @endif
        </div>

        {{-- Listeye dönüş: süzgeç ve sayfa numarası korunur. --}}
        <a href="{{ $this->donusAdresi }}"
           style="margin-left:auto; font-size:.85rem; display:inline-flex; align-items:center; gap:.3rem;">
            <x-filament::icon icon="heroicon-m-arrow-left" style="width:1rem; height:1rem;" />
            Listeye dön
        </a>
    </div>

    {{-- ── 2) Künye ─────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Künye</x-slot>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr)); gap:.9rem 2rem;">
            @foreach ($this->kunye() as $etiket => $alan)
                @php
                    $deger = is_array($alan) ? ($alan['deger'] ?? null) : $alan;
                    $kopyala = is_array($alan) ? ($alan['kopyala'] ?? false) : false;
                @endphp
                <div style="min-width:0;">
                    <div style="font-size:.72rem; letter-spacing:.04em; text-transform:uppercase; opacity:.55;">
                        {{ $etiket }}
                    </div>
                    <div style="margin-top:.15rem; font-size:.9rem; overflow-wrap:anywhere;
                                display:flex; align-items:center; gap:.4rem;">
                        <span>{{ filled($deger) ? $deger : '—' }}</span>
                        @if ($kopyala && filled($deger))
                            {{-- Kopyalama panelde en sık istenen küçük kolaylık:
                                 kart no ve e-posta telefonda okunuyor. --}}
                            <button type="button" title="Kopyala"
                                    x-data="{ alindi: false }"
                                    @click="navigator.clipboard.writeText(@js($deger)); alindi = true; setTimeout(() => alindi = false, 1200)"
                                    style="border:0; background:none; cursor:pointer; padding:0; opacity:.5;">
                                {{-- Unicode "⧉" panelin fontunda YOK, boş kutu çiziyordu. --}}
                                <span x-show="! alindi" style="display:inline-flex;">
                                    <x-filament::icon icon="heroicon-m-clipboard-document"
                                                      style="width:1rem; height:1rem;" />
                                </span>
                                <span x-show="alindi" x-cloak style="font-size:.75rem;">alındı</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- ── 2b) Uyarı bandı ──────────────────────────────────────
         Sekmeye girmeden görülmesi gerekenler (bkz. DetaySayfasi::uyariBandi).
         Künyenin hemen altında, sekmelerin üstünde. --}}
    @php $bant = $this->uyariBandi(); @endphp
    @if ($bant)
        <x-filament::section compact>
            <div style="display:flex; gap:.7rem; align-items:flex-start;">
                <x-filament::icon :icon="$bant['ikon'] ?? 'heroicon-m-exclamation-triangle'"
                                  @class(['fi-color-'.($bant['renk'] ?? 'warning')])
                                  style="width:1.35rem; height:1.35rem; flex:none; margin-top:.1rem;" />
                <div style="min-width:0; flex:1;">
                    <div style="font-weight:600;">{{ $bant['baslik'] }}</div>
                    @if (filled($bant['metin'] ?? null))
                        <p style="margin:.25rem 0 0; font-size:.85rem; opacity:.75;">{{ $bant['metin'] }}</p>
                    @endif
                </div>
                @if ($bant['baglanti'] ?? null)
                    <x-filament::link :href="$bant['baglanti']['url']" size="sm">
                        {{ $bant['baglanti']['etiket'] }}
                    </x-filament::link>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- ── 3) İlişkili kayıtlar ─────────────────────────────────── --}}
    @php $sekmeler = $this->sekmeler(); @endphp
    @if ($sekmeler !== [])
        <div x-data="{ acik: @js(array_key_first($sekmeler)) }">
            <x-filament::tabs>
                @foreach ($sekmeler as $anahtar => $sekme)
                    <x-filament::tabs.item alpine-active="acik === '{{ $anahtar }}'"
                                           x-on:click="acik = '{{ $anahtar }}'"
                                           :badge="$sekme['rozet'] ?? null">
                        {{ $sekme['baslik'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            @foreach ($sekmeler as $anahtar => $sekme)
                <div x-show="acik === '{{ $anahtar }}'" x-cloak style="margin-top:1rem;">
                    @include($sekme['view'], $sekme['veri'] ?? [])
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── 4) Denetim izi: her sayfanın en altında, aynı biçimde ── --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Denetim izi</x-slot>

        @php $kayitlar = $this->denetimKayitlari(); @endphp
        @if ($kayitlar->isEmpty())
            <p style="font-size:.85rem; opacity:.6;">Bu kayıt için henüz denetim kaydı yok.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:.6rem;">
                @foreach ($kayitlar as $k)
                    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:baseline;
                                font-size:.85rem; padding-bottom:.6rem;
                                border-bottom:1px solid rgba(127,127,127,.15);">
                        <span style="opacity:.55; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            {{ $k->created_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i') }}
                        </span>
                        <span style="font-weight:500;">{{ $k->olay }}</span>
                        <span style="opacity:.65;">{{ $k->aktor_ad ?? 'Sistem' }}</span>
                        @if (filled($k->not))
                            <span style="flex:1 1 100%; opacity:.75; overflow-wrap:anywhere;">{{ $k->not }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
