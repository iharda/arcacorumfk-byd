{{-- Kulüp duyuruları — üye ve kurum panellerinde aynı ekran.
     ⚠️ Panelde kendi Tailwind sınıflarımız derlenmez; satır içi stil. --}}
<x-filament-panels::page>

    @forelse ($this->duyurular as $duyuru)
        <x-filament::section>
            <x-slot name="heading">{{ $duyuru->baslik }}</x-slot>
            <x-slot name="description">
                {{ optional($duyuru->yayin_at)->timezone('Europe/Istanbul')?->format('d.m.Y H:i') }}
            </x-slot>

            @if ($duyuru->gorsel_yolu)
                <img src="{{ route('icerik.dosya', ['yol' => $duyuru->gorsel_yolu]) }}"
                     alt="" loading="lazy"
                     style="width:100%; max-height:22rem; object-fit:cover; border-radius:.6rem; margin-bottom:1rem;">
            @endif

            @if ($duyuru->video_yolu)
                {{-- 🎬 `preload="metadata"`: sayfada 10 duyuru birden var,
                     hepsinin videosu peşinen inmesin; yalnızca süre bilgisi.
                     Dosya `icerik.dosya` rotasından Range destekli akıyor. --}}
                <video controls preload="metadata" playsinline
                       style="width:100%; max-height:26rem; border-radius:.6rem; background:#000; margin-bottom:1rem;">
                    <source src="{{ route('icerik.dosya', ['yol' => $duyuru->video_yolu]) }}"
                            type="video/{{ strtolower(pathinfo($duyuru->video_yolu, PATHINFO_EXTENSION)) }}">
                    Tarayıcınız video oynatmıyor.
                    <a href="{{ route('icerik.dosya', ['yol' => $duyuru->video_yolu]) }}">Dosyayı açın</a>.
                </video>
            @endif

            @if (filled($duyuru->ozet))
                <p style="font-size:.95rem; font-weight:500; margin-bottom:.75rem;">{{ $duyuru->ozet }}</p>
            @endif

            @if (filled($duyuru->icerik))
                {{-- İçerik zengin metin editöründen geliyor; Filament çıktısı
                     temizlenmiş HTML. --}}
                <div class="fi-prose" style="font-size:.9rem; line-height:1.6;">
                    {!! $duyuru->icerik !!}
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="font-size:.9rem; opacity:.65;">Henüz yayınlanmış duyuru yok.</p>
        </x-filament::section>
    @endforelse

    @if ($this->duyurular->hasPages())
        <div>{{ $this->duyurular->links() }}</div>
    @endif

</x-filament-panels::page>
