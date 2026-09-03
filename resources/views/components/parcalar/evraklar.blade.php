{{-- Başvuru formundaki belge kutuları -- Revizyon md.3.1.
     Belge artık ayrı bir adımda değil, başvurunun kendisiyle birlikte alınır.

     🔑 Doğrulama hatasında dosya KAYBOLMAZ (Cüneyt Bey revizyonu 03.09.2026:
     "ya formu yeniden yükletmemeliyiz, ya da yüklense bile evrak seçimi
     yaptırmamalıyız"). Tarayıcı `<input type="file">` alanını `old()` ile geri
     dolduramaz; bu yüzden yüklenen dosya sunucuda taslak olarak tutulur
     ({@see \App\Servisler\EvrakTaslagi}) ve kutuda seçili görünür.

     🪤 Dosya girdisi GİZLENMEZ, saydamlaştırılıp düğmenin ÜSTÜNE serilir.
     `display:none` olsaydı `required` alan odaklanamaz olurdu; Chrome
     "An invalid form control is not focusable" deyip formu SESSİZCE
     göndermezdi. --}}
@props(['turler', 'taslaklar' => []])

<section>
    <h2 class="text-base font-semibold">Başvuru belgeleri</h2>

    <div class="mt-4 space-y-4">
        @foreach ($turler as $tur)
            @php
                $hata = $errors->first("evraklar.{$tur->id}");
                $bicimler = $tur->izinli_formatlar ?: ['pdf', 'jpg', 'jpeg', 'png'];
                $taslak = $taslaklar[$tur->id] ?? null;

                // "PDF, JPG, JPEG veya PNG" -- son biçim "veya" ile bağlanır.
                $bicimAdlari = array_map('strtoupper', $bicimler);
                $sonBicim = array_pop($bicimAdlari);
                $bicimMetni = $bicimAdlari === []
                    ? $sonBicim
                    : implode(', ', $bicimAdlari).' veya '.$sonBicim;
            @endphp

            <div @class([
                    'rounded-lg border px-4 py-3',
                    'border-neutral-200 bg-white' => ! $hata,
                    'border-kulup-600 bg-kulup-50' => (bool) $hata,
                 ])
                 x-data="{ secili: @js($taslak['ad'] ?? null), korunan: @js($taslak !== null) }">
                <label for="evrak-{{ $tur->id }}"
                       @class(['block text-sm font-medium text-neutral-800', 'zorunlu' => $tur->zorunlu])>
                    {{ $tur->ad }}
                </label>

                @if (filled($tur->aciklama))
                    <p class="mt-0.5 text-xs text-neutral-600">{{ $tur->aciklama }}</p>
                @endif

                <p class="mt-0.5 text-xs text-neutral-500">
                    {{ $bicimMetni }} · En fazla {{ intdiv($tur->maks_boyut_kb, 1024) }} MB
                    @if ($tur->hassas) · şifreli saklanır @endif
                </p>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <span class="relative inline-flex rounded-md bg-neutral-100 transition hover:bg-neutral-200
                                 focus-within:ring-2 focus-within:ring-kulup-600/30">
                        <span class="px-3 py-1.5 text-sm font-medium text-neutral-800">Dosya seç</span>
                        {{-- Taslakta dosya varsa tarayıcı "boş" diye tutturmasın:
                             sunucu zaten taslaktakini kullanacak. --}}
                        <input type="file" id="evrak-{{ $tur->id }}" name="evraklar[{{ $tur->id }}]"
                               accept="{{ collect($bicimler)->map(fn ($u) => '.'.$u)->implode(',') }}"
                               @if ($tur->zorunlu && $taslak === null) required @endif
                               @change="secili = $event.target.files[0]?.name ?? null; korunan = false"
                               class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                               aria-describedby="evrak-durum-{{ $tur->id }}">
                    </span>

                    {{-- 🪤 Başlangıç durumu SUNUCUDA doğru basılır, `x-cloak` yalnızca
                         YANLIŞ olacak yarıya konur. Hepsine koysaydık JS kapalıyken
                         satır komple boş kalır, hepsini açık bıraksaydık taslak
                         varken "Henüz dosya seçilmedi" bir an görünüp kaybolurdu. --}}
                    <span id="evrak-durum-{{ $tur->id }}" class="text-sm text-neutral-600">
                        <span x-show="! secili" @if ($taslak) x-cloak @endif>Henüz dosya seçilmedi</span>
                        <span x-show="secili" @if (! $taslak) x-cloak @endif
                              x-text="secili" class="font-medium text-koyu">{{ $taslak['ad'] ?? '' }}</span>
                        <span x-show="korunan" @if (! $taslak) x-cloak @endif class="ms-1 text-xs text-neutral-500">
                            (önceki seçiminiz korundu)
                        </span>
                    </span>
                </div>

                @if ($hata)
                    <p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
