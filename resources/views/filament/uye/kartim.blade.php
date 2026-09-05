{{-- Üye paneli — Kartım. Panelde kendi Tailwind sınıflarımız derlenmez;
     yerleşim satır içi stille. --}}
<x-filament-panels::page>

    @php $a = $this->akreditasyon; $mesaj = $this->durumMesaji(); $talep = $this->belgeTalebi; @endphp

    {{-- 🔑 Bekleyen belge talebi kartın ÜSTÜNDE. Kart sahibi panele kartına
         bakmaya gelir; talep yalnızca "Başvurum" sayfasında dursaydı görülmez,
         süre boşuna işlerdi. Şeridin ilk cümlesi kartın DURDUĞUNU söylüyor:
         "belge istendi" ifadesi tek başına akreditasyonun düştüğü gibi
         okunabiliyor. --}}
    @if ($talep)
        <x-filament::section>
            <x-slot name="heading">Belge bekleniyor</x-slot>
            <x-slot name="description">
                Kartınız geçerliliğini koruyor; bu talep akreditasyonunuzu askıya almaz.
            </x-slot>

            @if ($this->belgeTalebiSuresi())
                <p style="font-size:.875rem; font-weight:600;">{{ $this->belgeTalebiSuresi() }}</p>
            @endif

            <ul style="margin:.6rem 0 0; padding-left:1.15rem; font-size:.875rem;">
                @foreach ($talep->talep_notlari as $anahtar => $aciklama)
                    <li>
                        <span style="font-weight:600;">{{ $talep->basvuru->duzeltmeEtiketi($anahtar) }}</span>
                        @if (filled($aciklama))<span style="opacity:.75;"> — {{ $aciklama }}</span>@endif
                    </li>
                @endforeach
            </ul>

            <div style="margin-top:.9rem;">
                <x-filament::link :href="\App\Filament\Uye\Pages\Basvurum::getUrl()">
                    Belgeyi göndermek için Başvurum sayfasına gidin
                </x-filament::link>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Kart durumu</x-slot>
        @if ($a)
            <x-slot name="afterHeader">{{ $this->indirAction }}</x-slot>
        @endif

        <div style="display:flex; flex-wrap:wrap; gap:.75rem; align-items:center;">
            @if ($a)
                <x-filament::badge :color="$a->durum->renk()" size="lg">{{ $a->durum->etiket() }}</x-filament::badge>
                <span style="font-size:.9rem; font-weight:600;">{{ $a->kart_no }}</span>
                @if ($a->kurum)
                    <span style="font-size:.8rem; opacity:.65;">{{ $a->kurum->resmi_unvan }}</span>
                @endif
            @else
                <x-filament::badge color="gray" size="lg">Akreditasyon yok</x-filament::badge>
            @endif
        </div>

        @if ($mesaj)
            <p style="margin-top:.9rem; font-size:.875rem; opacity:.8;">{{ $mesaj }}</p>
        @endif
    </x-filament::section>

    @if ($this->gorselAdresi)
        <x-filament::section>
            <x-slot name="heading">Kartınız</x-slot>
            <x-slot name="description">
                Kapıda bu ekranı gösterebilir ya da PDF çıktısını alabilirsiniz.
            </x-slot>

            {{-- Kart 90×130 mm; ekranda oranı korunarak gösterilir. --}}
            <div style="display:flex; justify-content:center;">
                <img src="{{ $this->gorselAdresi }}" alt="Basın kartı"
                     style="width:100%; max-width:22rem; height:auto; display:block;
                            border-radius:.75rem; box-shadow:0 6px 24px rgb(0 0 0 / .14);">
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
