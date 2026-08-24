{{-- Başvuru formundaki evrak kutuları -- Revizyon md.3.1.
     Evrak artık ayrı bir adımda değil, başvurunun kendisiyle birlikte alınır.

     🪤 Dosya girdisi `old()` ile geri DOLDURULAMAZ (HTML böyle). Doğrulama
     hatasından sonra kullanıcı dosyaları yeniden seçmek zorunda; bu bir hata
     bildirimi olduğu için yazılır. --}}
@props(['turler'])

<section>
    <h2 class="text-base font-semibold">Evraklar</h2>

    @if ($errors->any())
        <p class="mt-1 text-xs text-kulup-700">Form yeniden yüklendiği için dosyaları tekrar seçmelisiniz.</p>
    @endif

    <div class="mt-4 space-y-4">
        @foreach ($turler as $tur)
            @php
                $hata = $errors->first("evraklar.{$tur->id}");
                $bicimler = $tur->izinli_formatlar ?: ['pdf', 'jpg', 'jpeg', 'png'];
            @endphp

            <div @class([
                'rounded-lg border px-4 py-3',
                'border-neutral-200 bg-white' => ! $hata,
                'border-kulup-600 bg-kulup-50' => (bool) $hata,
            ])>
                <label for="evrak-{{ $tur->id }}"
                       @class(['block text-sm font-medium text-neutral-800', 'zorunlu' => $tur->zorunlu])>
                    {{ $tur->ad }}
                </label>

                <p class="mt-0.5 text-xs text-neutral-500">
                    {{ strtoupper(implode(' · ', $bicimler)) }} · en fazla {{ intdiv($tur->maks_boyut_kb, 1024) }} MB
                    @if ($tur->hassas) · şifreli saklanır @endif
                </p>

                <input type="file" id="evrak-{{ $tur->id }}" name="evraklar[{{ $tur->id }}]"
                       accept="{{ collect($bicimler)->map(fn ($u) => '.'.$u)->implode(',') }}"
                       @if ($tur->zorunlu) required @endif
                       class="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border-0
                              file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium
                              hover:file:bg-neutral-200">

                @if ($hata)
                    <p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
