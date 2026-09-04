{{-- Başvuru ve evraklar sekmesi. Evrak önizlemesi S2 bileşeniyle: aynı
     görüntüleyici inceleme ekranında da, düzeltme ekranında da kullanılıyor. --}}
@if (! $basvuru)
    <x-filament::section>
        <p style="font-size:.85rem; opacity:.7;">Bu akreditasyona bağlı başvuru bulunamadı.</p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">Başvuru</x-slot>
        <x-slot name="afterHeader">
            <x-filament::link
                :href="\App\Filament\Yonetim\Resources\Basvurus\BasvuruResource::getUrl('inceleme', ['record' => $basvuru])"
                size="sm">
                İnceleme ekranını aç
            </x-filament::link>
        </x-slot>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr)); gap:.9rem 2rem; font-size:.9rem;">
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Başvuru no</div>
                <div>{{ $basvuru->basvuru_no ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Tür</div>
                <div>{{ $basvuru->tur->etiket() }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Durum</div>
                <div>{{ $basvuru->durum->etiket() }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Gönderim</div>
                <div>{{ $basvuru->gonderildi_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i') ?? '—' }}</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Evraklar</x-slot>

        @if ($evraklar->isEmpty())
            <p style="font-size:.85rem; opacity:.6;">Bu başvuruda evrak yok.</p>
        @else
            <div x-data="{ secili: @js($evraklar->first()?->id) }"
                 style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,2fr); gap:1rem;">
                <div style="display:flex; flex-direction:column; gap:.4rem;">
                    @foreach ($evraklar as $e)
                        {{-- 🪤 Alpine'ın `:style`'ı dize aldığında statik `style`
                             ÖZNİTELİĞİNİ TAMAMEN DEĞİŞTİRİYOR: ikisi ayrı yazılınca
                             kenarlık ve hizalama kayboluyordu. Tümü tek ifadede. --}}
                        <button type="button" @click="secili = {{ $e->id }}"
                                :style="'display:block; width:100%; text-align:left; border:1px solid;'
                                    + 'border-radius:.5rem; padding:.55rem .7rem; background:none; cursor:pointer;'
                                    + 'border-color:' + (secili === {{ $e->id }}
                                        ? 'rgba(127,127,127,.55)' : 'rgba(127,127,127,.2)')">
                            <div style="font-size:.85rem; font-weight:500;">
                                {{ $e->turu?->ad ?? 'Evrak' }}
                                @if ($e->imhaEdildiMi())
                                    <x-filament::badge color="gray" size="xs" style="display:inline-flex; vertical-align:middle;">
                                        İmha edildi
                                    </x-filament::badge>
                                @endif
                            </div>
                            <div style="font-size:.72rem; opacity:.6; overflow-wrap:anywhere;">{{ $e->orijinal_ad }}</div>
                        </button>
                    @endforeach
                </div>

                <div style="min-width:0;">
                    @foreach ($evraklar as $e)
                        <div x-show="secili === {{ $e->id }}" x-cloak>
                            <x-parcalar.dosya-onizleme
                                :kaynak="route('evrak.goster', $e)"
                                :mime="$e->mime"
                                :ad="$e->orijinal_ad"
                                :boyut="$e->boyut"
                                :imha="$e->imhaEdildiMi()"
                                :imha-tarihi="$e->imha_edildi_at"
                                yukseklik="min(60vh, 700px)" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
@endif
