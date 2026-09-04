{{-- Başvuru ve evraklar sekmesi. Evrak önizlemesi S2 bileşeniyle: aynı
     görüntüleyici inceleme ekranında da, düzeltme ekranında da kullanılıyor. --}}
@if (! $basvuru)
    <x-filament::section>
        <p style="font-size:.85rem; opacity:.7;">Bu akreditasyona bağlı başvuru bulunamadı.</p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">Başvuru</x-slot>
        <x-slot name="afterHeader">
            <x-filament::link
                :href="\App\Filament\Yonetim\Resources\Basvurus\BasvuruResource::getUrl('inceleme', ['record' => $basvuru])"
                size="sm">
                İnceleme ekranını aç
            </x-filament::link>
        </x-slot>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr)); gap:.9rem 2rem; font-size:.9rem;">
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Başvuru no</div>
                <div>{{ $basvuru->basvuru_no ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Tür</div>
                <div>{{ $basvuru->tur->etiket() }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Durum</div>
                <div>{{ $basvuru->durum->etiket() }}</div>
            </div>
            <div>
                <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Gönderim</div>
                <div>{{ $basvuru->gonderildi_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i') ?? '—' }}</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Evraklar</x-slot>

        <x-parcalar.evrak-listesi :evraklar="$evraklar" />
    </x-filament::section>
@endif
