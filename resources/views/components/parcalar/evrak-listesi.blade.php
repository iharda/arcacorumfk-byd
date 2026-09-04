{{-- Evrak listesi + önizleme -- Tutarsızlık incelemesi M2.4 md.7.

     🔑 TEK YERDE. Aynı mantık üç Blade'de kopyalanmıştı ve kopyalar zaten
     ayrışmaya başlamıştı: biri `ek_etiket`i basmıyordu, biri imha edilmiş
     evrakı sormuyordu. Yeni bir ekran (kurum/kullanıcı detayı) eklenince
     dördüncü kopya çıkacaktı.

     Soldan seç, sağda gör. Seçim ALPINE ile: Livewire gidiş-dönüşü olmadan
     anında değişir ve bileşen sayfa sınıfından bağımsız kalır -- inceleme
     ekranındaki `wire:click` sürümü sayfaya bağlıydı, buraya taşınamazdı.

     🎨 Satır içi stil kasıtlı: panelde uygulamanın Tailwind sınıfları
     derlenmiyor (bkz. dosya-onizleme bileşeni).

     ⚠️ `$evraklar` çağrılmadan ÖNCE `with('turu')` ile yüklenmeli; başlık
     her satırda evrak türüne bakıyor. --}}
@props([
    'evraklar',
    'yukseklik' => 'min(60vh, 700px)',
    'bosMesaj' => 'Bu başvuruda evrak yok.',
])

@if ($evraklar->isEmpty())
    <p style="font-size:.85rem; opacity:.6;">{{ $bosMesaj }}</p>
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
                        {{ $e->ekranBasligi() }}
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
                        :yukseklik="$yukseklik" />
                </div>
            @endforeach
        </div>
    </div>
@endif
