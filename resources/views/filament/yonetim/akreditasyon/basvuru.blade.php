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

    {{-- Açık belge talebi: ne istendi, ne zamana kadar, geldi mi. Üstteki
         uyarı bandı "bir şey bekleniyor" der; ayrıntısı burada. --}}
    @if ($talep ?? null)
        <x-filament::section style="margin-top:1rem;">
            <x-slot name="heading">{{ $talep->baslik() }}</x-slot>
            <x-slot name="description">
                {{ $talep->talep_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} tarihinde istendi
                @if ($talep->son_tarih)
                    · son gün {{ $talep->son_tarih->timezone('Europe/Istanbul')->format('d.m.Y') }}
                @endif
            </x-slot>

            @if ($talep->suresiGectiMi())
                {{-- ⚠️ Rozet YAPTIRIM DEĞİL. Süre dolunca sistem kartı askıya
                     almaz, talebi kapatmaz; ne yapılacağına yetkili karar
                     verir (Cüneyt Bey, 05.09.2026). --}}
                <x-filament::badge color="danger">
                    Süresi {{ abs($talep->kalanGun()) }} gün önce doldu — kararı siz verin
                </x-filament::badge>
            @endif

            <ul style="margin:.75rem 0 0; display:flex; flex-direction:column; gap:.5rem;">
                @foreach ($talep->maddeler() as $madde)
                    <li style="display:flex; gap:.6rem; align-items:flex-start;">
                        <x-filament::badge :color="$madde['degisti'] ? 'success' : 'warning'">
                            {{ $madde['etiket'] }}
                        </x-filament::badge>
                        <span style="font-size:.875rem;">{{ $madde['aciklama'] }}</span>
                    </li>
                @endforeach
            </ul>

            @if (filled($talep->talep_gerekcesi))
                <p style="margin-top:.9rem; font-size:.875rem; opacity:.8;">{{ $talep->talep_gerekcesi }}</p>
            @endif
        </x-filament::section>
    @endif

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Evraklar</x-slot>

        <x-parcalar.evrak-listesi :evraklar="$evraklar" />
    </x-filament::section>
@endif
