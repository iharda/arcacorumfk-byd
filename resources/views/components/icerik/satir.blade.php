{{-- Medya merkezi liste satırı — duyuru ve bülten aynı satırı kullanır.

     ⚠️ Bu görünüm PANEL TEMASI olmadan çalışmaz: `line-clamp-2`, `size-1.5`,
     `dark:bg-white/5` gibi sınıflar Filament'in hazır paketinde yok. Tema
     kurulmamışsa dosya hata vermez, sessizce dağılır. --}}
@props([
    'baslik',
    'tarih',
    'ozet' => null,
    'yeni' => false,
    'adres',
])

<a href="{{ $adres }}" wire:navigate
   @class([
       'flex items-start gap-4 px-icerik-kutu py-icerik-satir transition',
       'border-b border-gray-100 last:border-0 dark:border-white/5',
       'hover:bg-gray-50 dark:hover:bg-white/5',
       'focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600',
       'icerik-satir--yeni' => $yeni,
   ])>

    @isset($kapak)
        <div class="w-[6.5rem] flex-none overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5"
             style="aspect-ratio: var(--aspect-kapak-liste)">
            {{ $kapak }}
        </div>
    @endisset

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
            {{-- ♿ Renk tek başına bilgi taşımaz: şeridin YANINDA "Yeni"
                 rozeti de basılır. --}}
            @if ($yeni)
                <span class="size-1.5 rounded-full bg-primary-600"></span>
            @endif

            <time class="tabular-nums" datetime="{{ $tarih->toIso8601String() }}">
                {{ $tarih->timezone('Europe/Istanbul')->format('d.m.Y · H:i') }}
            </time>

            @if ($yeni)
                <x-filament::badge color="danger" size="xs">Yeni</x-filament::badge>
            @endif

            {{ $rozetler ?? '' }}
        </div>

        <h3 class="mt-1 line-clamp-2 text-sm font-semibold text-gray-950 dark:text-white">
            {{ $baslik }}
        </h3>

        @if (filled($ozet))
            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $ozet }}</p>
        @endif

        {{ $ekler ?? '' }}
    </div>

    <x-filament::icon icon="heroicon-m-chevron-right"
                      class="size-4 flex-none self-center text-gray-300 dark:text-gray-600" />
</a>
