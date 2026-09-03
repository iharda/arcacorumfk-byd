{{-- Basın bültenleri — duyurularla aynı satır ve detay bileşenleri.
     Ekler yalnızca akredite kullanıcıya açık `icerik.dosya` rotasından gelir. --}}
<x-filament-panels::page>

    @if ($this->acikBulten)
        @php($bulten = $this->acikBulten)
        <x-icerik.detay :baslik="$bulten->baslik"
                        :tarih="$bulten->yayin_at"
                        :icerik="$bulten->icerik"
                        :geri-adres="request()->fullUrlWithQuery(['acik' => null])"
                        geri-etiket="Bültenlere dön">
            <x-slot name="ekler">
                {{-- Ek yerleşimi tek bileşende (S3): aynı satır biçimi yönetim
                     panelindeki bülten detayında da kullanılıyor. --}}
                <x-parcalar.ekler :ekler="$bulten->ekler" />
            </x-slot>
        </x-icerik.detay>
    @else
        <x-filament::section>
            <x-slot name="heading">Bültenler</x-slot>
            <x-slot name="description">{{ $this->bultenler->total() }} bülten</x-slot>

            <x-slot name="afterHeader">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="max-w-64">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="arama"
                                       placeholder="Başlıkta ara…" />
                </x-filament::input.wrapper>
            </x-slot>

            <div class="-mx-6 -my-4">
                @forelse ($this->bultenler as $bulten)
                    {{-- Bültende kapak yok: kapak yuvası boş bırakılıyor, satır
                         metinle başlıyor. --}}
                    <x-icerik.satir :baslik="$bulten->baslik"
                                    :tarih="$bulten->yayin_at"
                                    :yeni="$this->yeniMi($bulten)"
                                    :adres="request()->fullUrlWithQuery(['acik' => $bulten->ulid])">
                        @if (filled($bulten->ekler))
                            <x-slot name="rozetler">
                                <x-filament::badge color="gray" size="xs">
                                    {{ count($bulten->ekler) }} ek
                                </x-filament::badge>
                            </x-slot>
                        @endif
                    </x-icerik.satir>
                @empty
                    <div class="px-6 py-10">
                        @if (filled($this->arama))
                            <x-filament::empty-state
                                icon="heroicon-o-magnifying-glass"
                                heading="Eşleşen bülten yok"
                                description="Aramanızla eşleşen bir bülten bulunamadı.">
                                <x-slot name="footer">
                                    <x-filament::button wire:click="aramayiTemizle" color="gray" size="sm">
                                        Aramayı temizle
                                    </x-filament::button>
                                </x-slot>
                            </x-filament::empty-state>
                        @else
                            <x-filament::empty-state
                                icon="heroicon-o-newspaper"
                                heading="Henüz bülten yayınlanmadı"
                                description="Kulüp bir basın bülteni yayınladığında burada görünür ve e-posta alırsınız." />
                        @endif
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        @if ($this->bultenler->hasPages())
            <div>{{ $this->bultenler->links() }}</div>
        @endif
    @endif

</x-filament-panels::page>
