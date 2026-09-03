{{-- Kulüp duyuruları — üye ve kurum panellerinde aynı ekran.

     Sayfanın işi TEK: başlıklar taransın, ilgilenilen açılsın. Ağır medya
     (görsel, video, tam metin) listede DEĞİL, detayda kurulur -- eskiden on
     duyurunun tamamı tam boy basılıyordu, tek sayfada on `<video>` vardı. --}}
<x-filament-panels::page>

    @if ($this->acikDuyuru)
        @php($duyuru = $this->acikDuyuru)
        <x-icerik.detay :baslik="$duyuru->baslik"
                        :tarih="$duyuru->yayin_at"
                        :ozet="$duyuru->ozet"
                        :icerik="$duyuru->icerik"
                        :gorsel="$duyuru->gorsel_yolu"
                        :video="$duyuru->video_yolu"
                        :geri-adres="request()->fullUrlWithQuery(['acik' => null])"
                        geri-etiket="Duyurulara dön" />
    @else
        <x-filament::section>
            <x-slot name="heading">Duyurular</x-slot>
            <x-slot name="description">
                {{ trans_choice(':sayi duyuru|:sayi duyuru', $this->duyurular->total(), ['sayi' => $this->duyurular->total()]) }}
            </x-slot>

            {{-- 🪤 Bölümün başlık yanı slotu `afterHeader` (headerEnd değil). --}}
            <x-slot name="afterHeader">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="max-w-64">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="arama"
                                       placeholder="Başlıkta ara…" />
                </x-filament::input.wrapper>
            </x-slot>

            {{-- 🪤 Bölümün kendi dolgusu kapatılıyor: satırlar kenardan kenara
                 uzanmalı, yoksa ayırıcı çizgiler havada asılı kalır. --}}
            <div class="-mx-6 -my-4">
                @forelse ($this->duyurular as $duyuru)
                    <x-icerik.satir :baslik="$duyuru->baslik"
                                    :tarih="$duyuru->yayin_at"
                                    :ozet="$duyuru->ozet"
                                    :yeni="$this->yeniMi($duyuru)"
                                    :adres="request()->fullUrlWithQuery(['acik' => $duyuru->ulid])">
                        <x-slot name="kapak">
                            <x-icerik.kapak :gorsel="$duyuru->gorsel_yolu" :video="$duyuru->video_yolu" />
                        </x-slot>

                        @if ($duyuru->video_yolu)
                            <x-slot name="rozetler">
                                <x-filament::badge color="gray" size="xs">Video</x-filament::badge>
                            </x-slot>
                        @endif
                    </x-icerik.satir>
                @empty
                    <div class="px-6 py-10">
                        @if (filled($this->arama))
                            <x-filament::empty-state
                                icon="heroicon-o-magnifying-glass"
                                heading="Eşleşen duyuru yok"
                                description="Aramanızla eşleşen bir duyuru bulunamadı.">
                                <x-slot name="footer">
                                    <x-filament::button wire:click="aramayiTemizle" color="gray" size="sm">
                                        Aramayı temizle
                                    </x-filament::button>
                                </x-slot>
                            </x-filament::empty-state>
                        @else
                            <x-filament::empty-state
                                icon="heroicon-o-megaphone"
                                heading="Henüz duyuru yayınlanmadı"
                                description="Kulüp bir duyuru yayınladığında burada görünür ve e-posta alırsınız." />
                        @endif
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        @if ($this->duyurular->hasPages())
            <div>{{ $this->duyurular->links() }}</div>
        @endif
    @endif

</x-filament-panels::page>
