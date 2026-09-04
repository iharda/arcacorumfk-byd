{{-- Kurum detayı · Evraklar sekmesi -- Tutarsızlık incelemesi M2.4 md.1.

     💀 Onaylanmış bir kurumun Ticaret Sicili Gazetesi'ne ulaşmanın tek yolu
     Kurumlar → detay → Başvuru geçmişi → numaraya tıkla → inceleme ekranı idi.
     Kurumsal onayda akreditasyon kaydı doğmadığı için bu evrakların başka bir
     evi de yoktu.

     Liste ve önizleme ortak bileşende (S2 / M2.4 md.7); bu dosya yalnızca
     hangi başvurunun evrakları olduğunu söyler. --}}
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

        {{-- Hangi başvurudan geldiği YAZILI olmalı: kurumun birden fazla
             başvurusu olabilir ve yetkili "bu evrak hangisinin" diye sorar. --}}
        <p style="font-size:.78rem; opacity:.6; margin:0 0 .75rem;">
            {{ $basvuru->basvuru_no ?? 'Numarasız başvuru' }} ·
            {{ $basvuru->durum->etiket() }}
            @if ($basvuru->gonderildi_at)
                · {{ $basvuru->gonderildi_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
            @endif
        </p>
    @endif

    <x-parcalar.evrak-listesi
        :evraklar="$evraklar"
        bos-mesaj="Bu kuruma ait kurumsal başvuru evrakı yok." />
</x-filament::section>
