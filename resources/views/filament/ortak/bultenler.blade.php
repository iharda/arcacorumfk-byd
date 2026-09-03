{{-- Basın bültenleri. Ekler yalnızca akredite kullanıcıya açık rotadan gelir. --}}
<x-filament-panels::page>

    @forelse ($this->bultenler as $bulten)
        <x-filament::section>
            <x-slot name="heading">{{ $bulten->baslik }}</x-slot>
            <x-slot name="description">
                {{ optional($bulten->yayin_at)->timezone('Europe/Istanbul')?->format('d.m.Y H:i') }}
            </x-slot>

            @if (filled($bulten->icerik))
                <div class="fi-prose" style="font-size:.9rem; line-height:1.6;">
                    {!! $bulten->icerik !!}
                </div>
            @endif

            {{-- Ek yerleşimi tek bileşende (S3): aynı satır biçimi yönetim
                 panelindeki bülten detayında da kullanılıyor. --}}
            <x-parcalar.ekler :ekler="$bulten->ekler" />
        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="font-size:.9rem; opacity:.65;">Henüz yayınlanmış bülten yok.</p>
        </x-filament::section>
    @endforelse

    @if ($this->bultenler->hasPages())
        <div>{{ $this->bultenler->links() }}</div>
    @endif

</x-filament-panels::page>
