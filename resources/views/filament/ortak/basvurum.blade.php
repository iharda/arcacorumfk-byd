{{-- "Başvurum" — kurum ve üye panellerinde AYNI ekran.
     ⚠️ Filament panelinde KENDİ Tailwind sınıflarımız derlenmez; burada
     yalnızca Filament'in kendi bileşenleri (x-filament::…) ve panelin
     paketinde ZATEN bulunan yardımcı sınıflar kullanılır. Şüphede kalınca
     satır içi stil yaz. --}}
<x-filament-panels::page>

    {{-- Durum şeridi --}}
    <x-filament::section>
        <x-slot name="heading">{{ $basvuru->kurum?->resmi_unvan ?? $basvuru->tur->etiket() }}</x-slot>
        <x-slot name="description">Başvuru no: {{ $basvuru->ulid }}</x-slot>

        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem;">
            <x-filament::badge :color="$basvuru->durum->renk()" size="lg">
                {{ $basvuru->durum->etiket() }}
            </x-filament::badge>

            @if ($basvuru->kurumTeyidiBekliyorMu())
                <x-filament::badge color="warning">Kurum teyidi bekleniyor</x-filament::badge>
            @endif

            @if ($basvuru->akreditasyon)
                <x-filament::badge color="success">Kart no: {{ $basvuru->akreditasyon->kart_no }}</x-filament::badge>
            @endif

            @if ($basvuru->gonderildi_at)
                <span style="font-size:.8rem; opacity:.65;">
                    Gönderim: {{ $basvuru->gonderildi_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                </span>
            @endif
        </div>

        @if ($basvuru->durum === \App\Enums\BasvuruDurumu::Reddedildi && filled($basvuru->karar_gerekcesi))
            <div style="margin-top:1rem;">
                <x-filament::section compact>
                    <x-slot name="heading">Red gerekçesi</x-slot>
                    {{ $basvuru->karar_gerekcesi }}
                </x-filament::section>
            </div>
        @endif
    </x-filament::section>

    {{-- Alan bazlı düzeltme talebi --}}
    @if (filled($basvuru->duzeltme_notlari))
        <x-filament::section>
            <x-slot name="heading">Düzeltilmesi istenen noktalar</x-slot>
            <x-slot name="description">Yalnızca aşağıdaki maddeleri güncelleyip başvurunuzu yeniden gönderin.</x-slot>

            <ul style="display:flex; flex-direction:column; gap:.5rem;">
                @foreach ($basvuru->duzeltme_notlari as $alan => $aciklama)
                    <li style="display:flex; gap:.6rem; align-items:flex-start;">
                        <x-filament::badge color="warning">{{ $alan }}</x-filament::badge>
                        <span style="font-size:.875rem;">{{ $aciklama }}</span>
                    </li>
                @endforeach
            </ul>

            @if (filled($basvuru->karar_gerekcesi))
                <p style="margin-top:1rem; font-size:.875rem; opacity:.8;">{{ $basvuru->karar_gerekcesi }}</p>
            @endif
        </x-filament::section>
    @endif

    {{-- Evraklar --}}
    <x-filament::section>
        <x-slot name="heading">Evraklar</x-slot>

        <div style="display:flex; flex-direction:column; gap:1rem;">
            @foreach ($this->evrakTurleri as $tur)
                @php $yuklu = $basvuru->evraklar->firstWhere('evrak_turu_id', $tur->id); @endphp

                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; padding:.85rem 1rem;
                            border:1px solid rgb(var(--gray-200)); border-radius:.6rem;">
                    <div style="flex:1 1 16rem; min-width:12rem;">
                        <div style="font-size:.9rem; font-weight:600;">
                            {{ $tur->ad }}
                            @if ($tur->zorunlu)<span style="color:rgb(var(--danger-600));">*</span>@endif
                        </div>
                        <div style="font-size:.75rem; opacity:.6; margin-top:.15rem;">
                            {{ strtoupper(implode(' · ', $tur->izinli_formatlar ?? [])) }}
                            · en fazla {{ intdiv($tur->maks_boyut_kb, 1024) }} MB
                            @if ($tur->hassas) · şifreli saklanır @endif
                        </div>
                    </div>

                    @if ($yuklu)
                        <x-filament::badge color="success">Yüklendi</x-filament::badge>
                        <span style="font-size:.75rem; opacity:.6;">{{ $yuklu->orijinal_ad }}</span>
                    @else
                        <x-filament::badge color="gray">Bekliyor</x-filament::badge>
                    @endif

                    @if ($this->yuklenebilirMi)
                        <div style="display:flex; align-items:center; gap:.5rem; flex:0 0 auto;">
                            <input type="file" wire:model="dosyalar.{{ $tur->id }}"
                                   accept="{{ collect($tur->izinli_formatlar ?? [])->map(fn ($u) => '.' . $u)->implode(',') }}"
                                   style="font-size:.78rem; max-width:14rem;">
                            <x-filament::button size="sm" wire:click="yukle({{ $tur->id }})"
                                                wire:loading.attr="disabled" wire:target="dosyalar.{{ $tur->id }},yukle">
                                {{ $yuklu ? 'Değiştir' : 'Yükle' }}
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @if ($this->yuklenebilirMi)
        <div style="display:flex; justify-content:flex-end;">
            <x-filament::button wire:click="gonder" wire:loading.attr="disabled" size="lg">
                Başvuruyu gönder
            </x-filament::button>
        </div>
    @endif

</x-filament-panels::page>
