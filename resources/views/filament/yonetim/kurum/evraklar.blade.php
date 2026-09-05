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

    {{-- 🔑 "Eksik evrak var mı?" sorusunun sekme içindeki cevabı. Üstteki bant
         sayfayı açan herkesin gözüne girer; buraya giren de neyin beklendiğini
         KALEM KALEM görsün -- "belge istendi" tek başına yetmiyor. --}}
    @if ($eksikEvrakBasvurusu ?? null)
        <div style="margin-bottom:1rem; border:1px solid rgba(234,179,8,.35);
                    background:rgba(234,179,8,.08); border-radius:.6rem; padding:.7rem .85rem;">
            <div style="display:flex; gap:.5rem; align-items:center; font-weight:600; font-size:.88rem;">
                <x-filament::icon icon="heroicon-m-clock" style="width:1.1rem; height:1.1rem;" />
                Yüklenmeyi bekleyen evrak var
            </div>

            @if ($beklenenEvraklar !== [])
                <ul style="margin:.5rem 0 0; padding-left:1.1rem; font-size:.85rem;">
                    @foreach ($beklenenEvraklar as $kalem)
                        <li>{{ $kalem }}</li>
                    @endforeach
                </ul>
            @endif

            @php $duzeltme = $eksikEvrakBasvurusu->acikDuzeltme(); @endphp
            @if ($duzeltme?->talep_at)
                <p style="margin:.5rem 0 0; font-size:.78rem; opacity:.7;">
                    {{ $duzeltme->talep_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
                    tarihinde istendi.
                </p>
            @endif
        </div>
    @endif

    <x-parcalar.evrak-listesi
        :evraklar="$evraklar"
        bos-mesaj="Bu kuruma ait kurumsal başvuru evrakı yok." />
</x-filament::section>
