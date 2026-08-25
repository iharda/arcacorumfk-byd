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
                @php
                    // Görsel ve video ekranda oynar/görünür; gerisi bağlantı.
                    $gorselUzantilari = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $videoUzantilari = ['mp4', 'webm'];
                    $uzanti = fn ($ek) => strtolower(pathinfo($ek, PATHINFO_EXTENSION));
                @endphp

                @php $gomulu = collect($bulten->ekler)->filter(fn ($ek) => in_array($uzanti($ek), array_merge($gorselUzantilari, $videoUzantilari), true)); @endphp

                @if ($gomulu->isNotEmpty())
                    <div style="margin-top:1rem; display:grid; gap:.75rem;
                                grid-template-columns:repeat(auto-fit, minmax(min(100%, 22rem), 1fr));">
                        @foreach ($gomulu as $ek)
                            @if (in_array($uzanti($ek), $videoUzantilari, true))
                                {{-- 🎬 `preload="metadata"`: liste açılırken 10 video birden
                                     indirilmesin, yalnızca süre/poster bilgisi çekilsin. --}}
                                <video controls preload="metadata" playsinline
                                       style="width:100%; border-radius:.5rem; background:#000;">
                                    <source src="{{ route('icerik.dosya', ['yol' => $ek]) }}"
                                            type="video/{{ $uzanti($ek) }}">
                                    Tarayıcınız video oynatmıyor.
                                    <a href="{{ route('icerik.dosya', ['yol' => $ek]) }}">Dosyayı açın</a>.
                                </video>
                            @else
                                <a href="{{ route('icerik.dosya', ['yol' => $ek]) }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ route('icerik.dosya', ['yol' => $ek]) }}" alt="" loading="lazy"
                                         style="width:100%; border-radius:.5rem; border:1px solid rgb(var(--gray-200));">
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                @php $baglantilar = collect($bulten->ekler)->diff($gomulu); @endphp

                @if ($baglantilar->isNotEmpty())
                    <div style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:.5rem;">
                        @foreach ($baglantilar as $ek)
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
