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

            @if (filled($bulten->ekler))
                <div style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:.5rem;">
                    @foreach ($bulten->ekler as $ek)
                        <a href="{{ route('icerik.dosya', ['yol' => $ek]) }}" target="_blank" rel="noopener noreferrer"
                           style="display:inline-flex; align-items:center; gap:.4rem; font-size:.82rem;
                                  padding:.45rem .75rem; border:1px solid rgb(var(--gray-200));
                                  border-radius:.5rem; text-decoration:none;">
                            <span aria-hidden="true">📎</span>
                            {{ \Illuminate\Support\Str::afterLast($ek, '/') }}
                        </a>
                    @endforeach
                </div>
            @endif
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
