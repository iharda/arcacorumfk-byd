{{-- Evrak listesi + önizleme -- Tutarsızlık incelemesi M2.4 md.7, M6.3.

     🔑 TEK YERDE. Aynı mantık üç Blade'de kopyalanmıştı ve kopyalar zaten
     ayrışmaya başlamıştı: biri `ek_etiket`i basmıyordu, biri imha edilmiş
     evrakı sormuyordu.

     Soldan seç, sağda gör. Seçim ALPINE ile: Livewire gidiş-dönüşü olmadan
     anında değişir ve bileşen sayfa sınıfından bağımsız kalır.

     🎨 Satır içi stil kasıtlı: panelde uygulamanın Tailwind sınıfları
     derlenmiyor (bkz. dosya-onizleme bileşeni).

     ⚠️ `$evraklar` çağrılmadan ÖNCE `with('turu')` ile yüklenmeli; başlık
     her satırda evrak türüne bakıyor. --}}
@props([
    'evraklar',
    'yukseklik' => 'min(60vh, 700px)',
    'bosMesaj' => 'Bu başvuruda evrak yok.',
    // Liste ve önizleme AYNI kutuda mı (varsayılan) yoksa sayfa ızgarasının
    // iki ayrı sütununda mı duracak? İnceleme ekranı ikincisini kullanır.
    'sutunlar' => 'minmax(0,1fr) minmax(0,2fr)',
])

{{-- 🪤 Yerleşim MEDYA SORGUSUYLA, satır içi stille değil: dar ekranda liste ve
     önizleme YAN YANA sıkışıyordu (M6.3 md.6). Önizleme artık listenin hemen
     ALTINA geçiyor -- tablette kullanılabilir olsun. Sütun oranı özel
     değişkenle geliyor çünkü satır içi stil medya sorgusuna giremez. --}}
<style>
    .bys-evrak-listesi { display:grid; gap:1rem; align-items:start; grid-template-columns:minmax(0,1fr); }
    @media (min-width: 1024px) {
        .bys-evrak-listesi { grid-template-columns: var(--bys-evrak-sutunlar); }
    }
</style>

@if ($evraklar->isEmpty())
    <p style="font-size:.85rem; opacity:.6;">{{ $bosMesaj }}</p>
@else
    {{-- 🔑 KLAVYEYLE GEZİNME (M6.3 md.3): kuyruk gününde onlarca başvuru
         açılıyor; fareyle satır satır tıklamak en çok tekrar eden hareket.
         `.window` dinleyici ama yalnız yazı alanı dışındayken çalışır --
         yetkili not yazarken ok tuşları belgeyi değiştirmemeli. --}}
    <div x-data="{
            sira: @js($evraklar->pluck('id')->values()),
            secili: @js($evraklar->first()?->id),
            gec(yon) {
                const i = this.sira.indexOf(this.secili);
                if (i === -1) return;
                this.secili = this.sira[(i + yon + this.sira.length) % this.sira.length];
            },
            yazidaMi(e) {
                const t = e.target;
                return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
            },
         }"
         @keydown.arrow-left.window="if (! yazidaMi($event)) { $event.preventDefault(); gec(-1) }"
         @keydown.arrow-right.window="if (! yazidaMi($event)) { $event.preventDefault(); gec(1) }"
         class="bys-evrak-listesi"
         style="--bys-evrak-sutunlar: {{ $sutunlar }};">

        <div style="display:flex; flex-direction:column; gap:.4rem; min-width:0;">
            @foreach ($evraklar as $e)
                {{-- 🪤 Alpine'ın `:style`'ı dize aldığında statik `style`
                     ÖZNİTELİĞİNİ TAMAMEN DEĞİŞTİRİYOR: ikisi ayrı yazılınca
                     kenarlık ve hizalama kayboluyordu. Tümü tek ifadede. --}}
                <button type="button" @click="secili = {{ $e->id }}"
                        :style="'display:flex; gap:.55rem; align-items:center; width:100%; text-align:left;'
                            + 'border:1px solid; border-radius:.5rem; padding:.5rem .6rem;'
                            + 'background:none; cursor:pointer;'
                            + 'border-color:' + (secili === {{ $e->id }}
                                ? 'rgba(127,127,127,.55)' : 'rgba(127,127,127,.2)')">

                    {{-- 🔑 KÜÇÜK RESİM (M6.3 md.2): belgeyi ADINI OKUMADAN
                         tanımak için. Görselde gerçek küçük resim, diğerlerinde
                         tür rozeti.

                         ⚠️ HASSAS EVRAKTA KÜÇÜK RESİM YOK. Adres istendiği anda
                         `EvrakController` "evrak.goruntulendi" denetim kaydı
                         yazar; küçük resim koysaydık sayfayı AÇMAK, bakılmayan
                         her kimlik belgesi için erişim kaydı üretirdi ve KVKK
                         erişim izini kullanılamaz hâle gelirdi. Aynı sebeple
                         imha edilmiş evrakta da istenmez (adres 410 döner).
                         --}}
                    @php
                        $gorselMi = str_starts_with((string) $e->mime, 'image/')
                            && ! $e->imhaEdildiMi()
                            && ! ($e->turu?->hassas ?? false);
                        $rozet = strtoupper(pathinfo((string) $e->orijinal_ad, PATHINFO_EXTENSION)) ?: 'DOSYA';
                    @endphp

                    @if ($gorselMi)
                        <img src="{{ route('evrak.goster', $e) }}" alt="" loading="lazy"
                             style="width:2.75rem; height:2.75rem; object-fit:cover; border-radius:.35rem;
                                    flex:none; background:rgba(127,127,127,.1);">
                    @else
                        <span style="width:2.75rem; height:2.75rem; flex:none; border-radius:.35rem;
                                     display:flex; align-items:center; justify-content:center;
                                     background:rgba(127,127,127,.12); font-size:.6rem; font-weight:600;
                                     letter-spacing:.03em;">{{ mb_strimwidth($rozet, 0, 5, '') }}</span>
                    @endif

                    <span style="min-width:0;">
                        <span style="display:block; font-size:.85rem; font-weight:500;">
                            {{ $e->ekranBasligi() }}
                            @if ($e->imhaEdildiMi())
                                <x-filament::badge color="gray" size="xs" style="display:inline-flex; vertical-align:middle;">
                                    İmha edildi
                                </x-filament::badge>
                            @endif
                        </span>
                        <span style="display:block; font-size:.72rem; opacity:.6; overflow-wrap:anywhere;">
                            {{ $e->orijinal_ad }}
                        </span>
                    </span>
                </button>
            @endforeach

            @if ($evraklar->count() > 1)
                <p style="font-size:.7rem; opacity:.5; margin:.15rem 0 0;">← → tuşlarıyla geçiş yapabilirsiniz.</p>
            @endif
        </div>

        <div style="min-width:0;">
            @foreach ($evraklar as $e)
                {{-- 🔑 `x-show` DEĞİL `x-if`: `x-show` gizli olanı da ÇİZER,
                     yani sayfa açılır açılmaz her belgenin adresi istenirdi.
                     Hassas evrakta bu, BAKILMAYAN kimlik belgeleri için de
                     "görüntülendi" denetim kaydı yazmak demekti (KVKK erişim
                     kaydını kirletir). `x-if` seçilene kadar hiç istemez. --}}
                <template x-if="secili === {{ $e->id }}">
                    <div>
                        <x-parcalar.dosya-onizleme
                            :kaynak="route('evrak.goster', $e)"
                            :mime="$e->mime"
                            :ad="$e->orijinal_ad"
                            :boyut="$e->boyut"
                            :imha="$e->imhaEdildiMi()"
                            :imha-tarihi="$e->imha_edildi_at"
                            :yukseklik="$yukseklik" />
                    </div>
                </template>
            @endforeach
        </div>
    </div>
@endif
