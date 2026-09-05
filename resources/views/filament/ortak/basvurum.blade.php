{{-- "Başvurum" — kurum ve üye panellerinde AYNI ekran. SALT OKUNUR:
     evrak başvuru formunda alınır, eksik evrak panelsiz düzeltilir
     (Revizyon md.3.6).
     ⚠️ Filament panelinde KENDİ Tailwind sınıflarımız derlenmez; burada
     yalnızca Filament'in kendi bileşenleri (x-filament::…) ve panelin
     paketinde ZATEN bulunan yardımcı sınıflar kullanılır. Şüphede kalınca
     satır içi stil yaz. --}}
<x-filament-panels::page>

    {{-- Durum şeridi --}}
    <x-filament::section>
        <x-slot name="heading">{{ $basvuru->kurum?->resmi_unvan ?? $basvuru->tur->etiket() }}</x-slot>
        <x-slot name="description">Başvuru no: {{ $basvuru->basvuru_no ?? "—" }}</x-slot>

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

        @if ($basvuru->durum === \App\Enums\BasvuruDurumu::Reddedildi)
            @if (filled($basvuru->karar_gerekcesi))
                <div style="margin-top:1rem;">
                    <x-filament::section compact>
                        <x-slot name="heading">Red gerekçesi</x-slot>
                        {{ $basvuru->karar_gerekcesi }}
                    </x-filament::section>
                </div>
            @endif

            @php $yeniden = $this->yenidenBasvuru; @endphp

            @if ($yeniden['adres'])
                <div style="margin-top:1rem;">
                    <x-filament::button tag="a" :href="$yeniden['adres']" icon="heroicon-m-arrow-path" color="gray">
                        Yeniden başvur
                    </x-filament::button>
                </div>
            @elseif ($yeniden['engel'])
                <p style="margin-top:1rem; font-size:.875rem; opacity:.8;">{{ $yeniden['engel'] }}</p>
            @endif
        @endif
    </x-filament::section>

    {{-- 🔑 EKSİK EVRAK ŞERİDİ: sayfanın en üstünde, kaçırılmayacak yerde.
         Kuruluş "bir şey mi bekleniyor" sorusunu aşağı inmeden yanıtlasın. --}}
    @if ($this->eksikEvrakBekliyorMu())
        <div style="border:1px solid rgb(var(--warning-400)); background:rgb(var(--warning-50));
                    border-radius:.75rem; padding:1rem 1.15rem;">
            <div style="display:flex; gap:.7rem; align-items:flex-start;">
                <x-filament::icon icon="heroicon-m-exclamation-triangle"
                                  style="width:1.4rem; height:1.4rem; flex:none; margin-top:.1rem;" />
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700;">Eksik evrak bekleniyor</div>
                    <p style="margin:.3rem 0 0; font-size:.875rem;">
                        Başvurunuz için aşağıdaki belge veya bilgiler isteniyor. Tamamlamadan
                        başvurunuz değerlendirmeye alınmaz.
                    </p>

                    @if ($this->istenenKalemler !== [])
                        <ul style="margin:.6rem 0 0; padding-left:1.15rem; font-size:.875rem;">
                            @foreach ($this->istenenKalemler as $kalem)
                                <li>{{ $kalem }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div style="margin-top:.9rem;">
                        {{ $this->eksikEvrakAction }}
                    </div>

                    <p style="margin:.5rem 0 0; font-size:.75rem; opacity:.7;">
                        Yükleme sayfası açıldığında e-posta ile gönderilmiş eski bağlantı geçersiz olur.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- 🔑 BELGE TALEBİ ŞERİDİ (karar sonrası). Eksik evrak şeridinden AYRI
         çizilir çünkü söylediği şey neredeyse zıt: orada "tamamlamadan
         değerlendirmeye alınmaz", burada "kartınız aktif, sadece belge
         istiyoruz". İkisi tek şeride sığdırılsaydı akredite kişi
         akreditasyonunun düştüğünü sanırdı. --}}
    @if ($this->belgeTalebiBekliyorMu())
        @php $talep = $this->belgeTalebi(); @endphp
        <div style="border:1px solid rgb(var(--warning-400)); background:rgb(var(--warning-50));
                    border-radius:.75rem; padding:1rem 1.15rem;">
            <div style="display:flex; gap:.7rem; align-items:flex-start;">
                <x-filament::icon icon="heroicon-m-document-plus"
                                  style="width:1.4rem; height:1.4rem; flex:none; margin-top:.1rem;" />
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700;">Belge bekleniyor</div>
                    <p style="margin:.3rem 0 0; font-size:.875rem;">
                        Akreditasyonunuz kapsamında aşağıdaki belge veya bilgi isteniyor.
                        <strong>{{ $basvuru->belgeTalebiGuvencesi() }}</strong>; bu talep
                        akreditasyonunuzu askıya almaz.
                    </p>

                    @if ($this->belgeTalebiSuresi())
                        <p style="margin:.35rem 0 0; font-size:.875rem; font-weight:600;">
                            {{ $this->belgeTalebiSuresi() }}
                        </p>
                    @endif

                    @if ($this->istenenKalemler !== [])
                        <ul style="margin:.6rem 0 0; padding-left:1.15rem; font-size:.875rem;">
                            @foreach ($this->istenenKalemler as $kalem)
                                <li>{{ $kalem }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (filled($talep?->talep_gerekcesi))
                        <p style="margin:.6rem 0 0; font-size:.875rem; opacity:.85;">{{ $talep->talep_gerekcesi }}</p>
                    @endif

                    <div style="margin-top:.9rem;">
                        {{ $this->eksikEvrakAction }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Alan bazlı düzeltme talebi --}}
    @if (filled($basvuru->duzeltme_notlari) && ! $this->belgeTalebiBekliyorMu())
        <x-filament::section>
            <x-slot name="heading">Düzeltilmesi istenen noktalar</x-slot>
            <x-slot name="description">Yalnızca aşağıdaki maddeleri güncelleyip başvurunuzu yeniden gönderin.</x-slot>

            <ul style="display:flex; flex-direction:column; gap:.5rem;">
                @foreach ($basvuru->duzeltme_notlari as $alan => $aciklama)
                    <li style="display:flex; gap:.6rem; align-items:flex-start;">
                        <x-filament::badge color="warning">{{ $basvuru->duzeltmeEtiketi($alan) }}</x-filament::badge>
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
                {{-- 🪤 `firstWhere` DEĞİL. Ek talep belgeleri AYNI evrak türünü
                     paylaşır ve birbirinden yalnızca `ek_etiket` ile ayrılır
                     (EvrakYukleyici::yukle). Tek belge alınınca başvuran ikinci
                     ek belgesini hiçbir yerde göremiyordu. (M2.3 / M2.4 md.6) --}}
                @php $yuklenenler = $basvuru->evraklar->where('evrak_turu_id', $tur->id); @endphp

                @foreach ($yuklenenler->isEmpty() ? [null] : $yuklenenler as $yuklu)
                <div x-data="{ acik: false }"
                     style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; padding:.85rem 1rem;
                            border:1px solid rgb(var(--gray-200)); border-radius:.6rem;">
                    <div style="flex:1 1 16rem; min-width:12rem;">
                        <div style="font-size:.9rem; font-weight:600;">
                            {{-- Ek talep belgesinin başlığı burada; iki ek belge
                                 artık "Ek talep belgesi" diye aynı görünmüyor. --}}
                            {{ $yuklu?->ekranBasligi() ?? $tur->ad }}
                            {{-- Zorunluluk BU BAŞVURUYA göre: sonradan zorunlu olan bir
                                 belge eski başvuruda kırmızı yıldızla "eksik" görünmesin. --}}
                            @if ($tur->basvuruIcinZorunluMu($basvuru))<span style="color:rgb(var(--danger-600));">*</span>@endif
                        </div>
                        <div style="font-size:.75rem; opacity:.6; margin-top:.15rem;">
                            {{ strtoupper(implode(' · ', $tur->izinli_formatlar ?? [])) }}
                            · en fazla {{ intdiv($tur->maks_boyut_kb, 1024) }} MB
                            @if ($tur->hassas) · şifreli saklanır @endif
                        </div>
                    </div>

                    @if ($yuklu)
                        {{-- Başvuran da "belgem duruyor mu" sorusunun cevabını
                             görmeli: saklama süresi dolan hassas evrakın dosyası
                             KVKK gereği silinir, kaydı kalır (M2.2). --}}
                        @if ($yuklu->imhaEdildiMi())
                            <x-filament::badge color="gray">İmha edildi</x-filament::badge>
                        @else
                            <x-filament::badge color="success">Yüklendi</x-filament::badge>
                        @endif
                        <span style="font-size:.75rem; opacity:.6;">{{ $yuklu->orijinal_ad }}</span>
                        {{-- S4: yüklenen belge GERİ AÇILABİLİR olmalı. Eksik evrak
                             talebi gelince "ben ne yüklemiştim" sorusunun cevabı
                             yoktu; kişi aynı belgeyi yeniden bulup yüklüyordu.
                             Yetki tarafında yeni bir şey gerekmiyor:
                             EvrakPolicy::view sahibi zaten kontrol ediyor. --}}
                        <button type="button" @click="acik = ! acik"
                                style="border:0; background:none; padding:0; cursor:pointer;
                                       font-size:.8rem; text-decoration:underline;">
                            <span x-show="! acik">Görüntüle</span>
                            <span x-show="acik" x-cloak>Gizle</span>
                        </button>
                    @else
                        <x-filament::badge color="gray">Bekliyor</x-filament::badge>
                    @endif

                    @if ($yuklu)
                        <div x-show="acik" x-cloak style="flex:1 1 100%; margin-top:.35rem;">
                            <x-parcalar.dosya-onizleme
                                :kaynak="route('evrak.goster', $yuklu)"
                                :mime="$yuklu->mime"
                                :ad="$yuklu->orijinal_ad"
                                :boyut="$yuklu->boyut"
                                :imha="$yuklu->imhaEdildiMi()"
                                :imha-tarihi="$yuklu->imha_edildi_at"
                                yukseklik="min(55vh, 640px)" />
                        </div>
                    @endif
                </div>
                @endforeach
            @endforeach
        </div>
    </x-filament::section>

</x-filament-panels::page>
