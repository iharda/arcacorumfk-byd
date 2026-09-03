{{-- Kart sekmesi -- T9 (görsel + PDF) ve T10'un ikinci katmanı (sürüm geçmişi).

     🔒 Kart görselinin adresi HERKESE AÇIK DEĞİL: QR'ı içeriyor, rota
     policy'den geçiyor. Burada da aynı rota kullanılıyor, yeni bir kapı
     açılmıyor. --}}
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(20rem, 1fr)); gap:1.25rem;">

    <x-filament::section>
        <x-slot name="heading">Kart görseli</x-slot>

        @if ($guncel?->gorsel_yolu)
            {{-- Maç günü turnikede sorun çıktığında yetkilinin "kartında ne
                 yazıyor" diye telefonla sorması gerekiyordu; artık bakabiliyor. --}}
            <x-parcalar.dosya-onizleme
                :kaynak="route('kart.gorsel', $guncel)"
                mime="image/png"
                :ad="'basin-karti-'.$akreditasyon->kart_no.'.png'"
                :boyut="$guncel->boyut" />
        @elseif ($guncel)
            <p style="font-size:.85rem; opacity:.7;">
                Kart kaydı var ama görsel henüz üretilmedi. Üretim kuyrukta;
                bittiğinde zil ikonuna bildirim düşer.
            </p>
        @else
            <p style="font-size:.85rem; opacity:.7;">Bu akreditasyon için henüz kart üretilmedi.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Sürüm geçmişi</x-slot>

        @if ($surumler->isEmpty())
            <p style="font-size:.85rem; opacity:.6;">Kayıt yok.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:.55rem;">
                @foreach ($surumler as $k)
                    <div style="display:flex; gap:.65rem; flex-wrap:wrap; align-items:baseline; font-size:.85rem;
                                padding-bottom:.55rem; border-bottom:1px solid rgba(127,127,127,.15);">
                        <x-filament::badge :color="$k->arsiv ? 'gray' : 'success'">s{{ $k->surum }}</x-filament::badge>
                        <span style="opacity:.55; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            {{ $k->uretildi_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i') ?? 'Hazırlanıyor' }}
                        </span>
                        <span style="opacity:.65;">{{ $k->ureten?->name ?? 'Sistem' }}</span>
                        <span style="margin-left:auto; opacity:.5;">{{ \App\Support\DosyaBoyutu::metin($k->boyut) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
