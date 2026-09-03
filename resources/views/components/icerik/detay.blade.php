{{-- Duyuru / bülten detayı — liste sayfasının içinde açılır (ayrı rota yok).

     🔑 Ağır medya YALNIZCA burada kurulur ve tek kayıt için: sayfadaki
     `<video>` sayısı her zaman en fazla bir. --}}
@props([
    'baslik',
    'tarih' => null,
    'ozet' => null,
    'icerik' => null,
    'gorsel' => null,
    'video' => null,
    'geriAdres',
    'geriEtiket' => 'Listeye dön',
])

<div class="flex flex-col gap-4">
    <div>
        <x-filament::link :href="$geriAdres" wire:navigate icon="heroicon-m-arrow-left" size="sm">
            {{ $geriEtiket }}
        </x-filament::link>
    </div>

    <x-filament::section>
        @if ($video)
            {{-- 🎬 `preload="metadata"`: yalnızca süre bilgisi iner, dosyanın
                 kendisi oynatılana kadar bant harcamaz. Görsel varsa poster
                 olarak kullanılır. --}}
            <video controls preload="metadata" playsinline
                   class="w-full rounded-lg bg-black"
                   style="aspect-ratio: var(--aspect-kapak-detay)"
                   @if ($gorsel) poster="{{ route('icerik.dosya', ['yol' => $gorsel]) }}" @endif>
                <source src="{{ route('icerik.dosya', ['yol' => $video]) }}"
                        type="video/{{ strtolower(pathinfo($video, PATHINFO_EXTENSION)) }}">
                Tarayıcınız video oynatmıyor.
                <a href="{{ route('icerik.dosya', ['yol' => $video]) }}">Dosyayı açın</a>.
            </video>
        @elseif ($gorsel)
            <img src="{{ route('icerik.dosya', ['yol' => $gorsel]) }}"
                 alt="" class="w-full rounded-lg object-cover"
                 style="aspect-ratio: var(--aspect-kapak-detay)">
        @endif

        @if ($tarih)
            <time class="mt-4 block text-xs tabular-nums text-gray-400 dark:text-gray-500"
                  datetime="{{ $tarih->toIso8601String() }}">
                {{ $tarih->timezone('Europe/Istanbul')->format('d.m.Y · H:i') }}
            </time>
        @endif

        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $baslik }}</h2>

        @if (filled($ozet))
            <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $ozet }}</p>
        @endif

        @if (filled($icerik))
            {{-- Zengin metin editöründen geliyor; Filament çıktısı temizlenmiş
                 HTML (Ayar::yaz ve GuvenliHtml zincirinden geçmiş). --}}
            <div class="fi-prose icerik-metin mt-4 text-sm">{!! $icerik !!}</div>
        @endif

        {{ $ekler ?? '' }}
    </x-filament::section>
</div>
