{{-- Kullanıcı detayı · Evraklar sekmesi -- Tutarsızlık incelemesi M2.4 md.3.

     Kişinin kimlik belgesi ve çalışma belgesi yalnızca başvuru inceleme
     ekranında görünüyordu; onaydan sonra "bu kişi neyle akredite oldu"
     sorusunun cevabı hiçbir kullanıcı ekranında yoktu.

     ⚠️ Hassas evrak (kimlik görseli) at-rest şifreli saklanır ve erişimi
     denetime düşer (EvrakController::goster). Bu sekme yeni bir kapı AÇMAZ:
     dosyayı yine aynı yetki kontrolünden geçen adres sunar. --}}
<x-filament::section>
    <x-slot name="heading">Evraklar</x-slot>

    @if ($basvuru)
        <x-slot name="afterHeader">
            <x-filament::link
                :href="\App\Filament\Yonetim\Resources\Basvurus\BasvuruResource::getUrl('inceleme', ['record' => $basvuru])"
                size="sm">
                İnceleme ekranını aç
            </x-filament::link>
        </x-slot>

        {{-- Kişinin birden fazla başvurusu olabilir; hangisi olduğu yazılı olsun. --}}
        <p style="font-size:.78rem; opacity:.6; margin:0 0 .75rem;">
            {{ $basvuru->basvuru_no ?? 'Numarasız başvuru' }} ·
            {{ $basvuru->tur->etiket() }} ·
            {{ $basvuru->durum->etiket() }}
            @if ($basvuru->gonderildi_at)
                · {{ $basvuru->gonderildi_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
            @endif
        </p>
    @endif

    <x-parcalar.evrak-listesi
        :evraklar="$evraklar"
        bos-mesaj="Bu kişinin başvurusuna bağlı evrak yok." />
</x-filament::section>
