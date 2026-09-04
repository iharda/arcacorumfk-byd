{{-- Kurum detayı · Çalışanlar sekmesi (T5).

     🔑 Satır AKREDİTASYON durumunu gösterir (Cüneyt Bey revizyonu 05.09.2026).
     Eskiden yalnızca hesabın Aktif/Pasif olduğu yazıyordu; o, kişinin
     turnikeden geçip geçemeyeceği hakkında hiçbir şey söylemez. Kulübün
     sorduğu soru "bu çalışanın kartı geçerli mi".

     🔑 Ada tıklanınca kişinin detayına gidilir -- ama yalnızca görme yetkisi
     varsa. Kullanıcı detayı `kullanici.yonet` istiyor ve o yetki bilerek
     super'de; yetkisi olmayana 403'e giden bir bağlantı göstermiyoruz.

     ⚠️ Kurumun KENDİ hesabı bu listede yok; ayıklama KurumDetay::sekmeler()
     içinde (kurum yetkilisi çalışan değildir). --}}
<x-filament::section>
    @if ($calisanlar->isEmpty())
        <p style="font-size:.85rem; opacity:.7;">
            Bu kuruma bağlı çalışan yok — kurum yetkilisi davet gönderdiğinde buraya düşer.
        </p>
    @else
        <div style="display:flex; flex-direction:column; gap:.55rem;">
            @foreach ($calisanlar as $c)
                @php
                    $akreditasyon = $c->akreditasyon;
                    $adres = (auth()->user()?->can('view', $c) ?? false)
                        ? \App\Filament\Yonetim\Resources\Kullanicilar\KullaniciResource::getUrl('detay', ['record' => $c])
                        : null;
                @endphp

                <div style="display:flex; gap:.7rem; flex-wrap:wrap; align-items:baseline; font-size:.88rem;
                            padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                    <span style="font-weight:500;">
                        @if ($adres)
                            <a href="{{ $adres }}">{{ $c->name }}</a>
                        @else
                            {{ $c->name }}
                        @endif
                    </span>
                    <span style="opacity:.65;">{{ $c->email }}</span>

                    <span style="margin-left:auto; display:flex; gap:.4rem; align-items:center;">
                        {{-- Asıl bilgi: kartı ne durumda. --}}
                        @if ($akreditasyon)
                            <x-filament::badge :color="$akreditasyon->durum->renk()">
                                {{ $akreditasyon->durum->etiket() }}
                            </x-filament::badge>
                            <span style="font-size:.75rem; opacity:.6;">{{ $akreditasyon->kart_no }}</span>
                        @else
                            <x-filament::badge color="gray">Akreditasyonu yok</x-filament::badge>
                        @endif

                        {{-- Hesap durumu ayrı bir şey; pasifse ayrıca söylensin. --}}
                        @unless ($c->aktif)
                            <x-filament::badge color="gray">Hesap pasif</x-filament::badge>
                        @endunless
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
