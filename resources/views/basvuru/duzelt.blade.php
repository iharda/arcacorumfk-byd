@extends('layouts.kamu')
@section('baslik', 'Başvuru düzeltme')

@section('icerik')
<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Başvurunuzu düzeltin</h1>
    <p class="mt-2 text-sm text-neutral-600">
        {{ $basvuru->tur->etiket() }}@if ($basvuru->kurum) · {{ $basvuru->kurum->resmi_unvan }}@endif
    </p>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            @if ($errors->has('genel'))
                {{ $errors->first('genel') }}
            @else
                {{ $errors->count() }} alan kontrol edilmeli.
            @endif
        </div>
    @endif

    {{-- Yetkilinin işaretledikleri: başvuran neyi düzelteceğini burada görür. --}}
    <section class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold">
                {{ $duzeltme?->baslik() ?? 'Düzeltilmesi istenenler' }}
            </h2>
            @if ($duzeltme)
                <span class="text-xs text-neutral-500">
                    {{ $duzeltme->talep_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                </span>
            @endif
        </div>

        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($basvuru->duzeltme_notlari ?? [] as $alan => $aciklama)
                <li>
                    <span class="font-medium text-koyu">{{ $basvuru->duzeltmeEtiketi($alan) }}</span>
                    @if (filled($aciklama))
                        <span class="text-neutral-600">— {{ $aciklama }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if (filled($basvuru->karar_gerekcesi))
            <p class="mt-3 border-t border-neutral-200 pt-3 text-sm text-neutral-600">
                {{ $basvuru->karar_gerekcesi }}
            </p>
        @endif
    </section>

    {{-- Başvuru geçmişi: "ilk bilgiler · düzeltme 01 · düzeltme 02"
         (Yusuf revizyonu md.4).

         🪤 KOŞULSUZ çizilir. Eskiden yalnızca önceki tur VARSA görünüyordu,
         yani ilk turda "İlk bilgiler" hiç çıkmıyordu -- oysa çizelgenin
         başlangıç noktası tam da o. --}}
    <details class="mt-4 rounded-lg border border-neutral-200 px-4 py-3">
        <summary class="cursor-pointer text-sm font-medium">
            Başvuru geçmişi
            @if ($gecmisTurlar->isNotEmpty())
                ({{ $gecmisTurlar->count() }} düzeltme)
            @endif
        </summary>

            <div class="mt-4 space-y-5">
                {{-- ⏱️ Çizelge başvurunun kendisinden başlar (Yusuf md.4). --}}
                <div class="border-l-2 border-neutral-200 pl-4">
                    <p class="text-sm font-medium">
                        İlk bilgiler
                        <span class="ml-1 text-xs font-normal text-neutral-500">
                            {{ ($basvuru->gonderildi_at ?? $basvuru->created_at)?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                            · başvurunuz alındı
                        </span>
                    </p>

                    <ul class="mt-2 space-y-1 text-sm text-neutral-600">
                        @foreach ($basvuru->ilkDegerler() as $anahtar => $deger)
                            @continue($basvuru->duzeltmeDegeriGoster($anahtar, $deger) === '—')
                            <li>
                                <span class="font-medium text-koyu">{{ $basvuru->duzeltmeEtiketi($anahtar) }}</span>
                                — {{ $basvuru->duzeltmeDegeriGoster($anahtar, $deger) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                @foreach ($gecmisTurlar->sortBy('sira') as $gecmis)
                    <div class="border-l-2 border-neutral-200 pl-4">
                        <p class="text-sm font-medium">
                            {{ $gecmis->baslik() }}
                            <span class="ml-1 font-normal text-xs text-neutral-500">
                                istendi {{ $gecmis->talep_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                · yanıtlandı {{ $gecmis->yanit_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                            </span>
                        </p>

                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($gecmis->maddeler() as $madde)
                                <li>
                                    <span class="font-medium text-koyu">{{ $madde['etiket'] }}</span>
                                    @if (filled($madde['aciklama']))
                                        <span class="text-neutral-600">— {{ $madde['aciklama'] }}</span>
                                    @endif
                                    @if ($madde['degisti'])
                                        <span class="mt-0.5 block text-xs text-neutral-500">
                                            <span class="line-through">{{ $basvuru->duzeltmeDegeriGoster($madde['anahtar'], $madde['eski']) }}</span>
                                            <span class="mx-1">→</span>
                                            <span class="text-koyu">{{ $basvuru->duzeltmeDegeriGoster($madde['anahtar'], $madde['yeni']) }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if (filled($gecmis->yanit_aciklama))
                            <p class="mt-2 text-xs text-neutral-600">
                                Açıklamanız: {{ $gecmis->yanit_aciklama }}
                            </p>
                        @endif
                    </div>
                @endforeach
        </div>
    </details>

    <form method="POST" action="{{ route('basvuru.duzelt.kaydet', ['token' => $token]) }}"
          enctype="multipart/form-data" class="mt-8 space-y-8">
        @csrf

        {{-- Veri alanları: her işaretli alan KENDİ girdisiyle, önceki değeri
             yanında. Eskiden burada tek bir serbest metin kutusu vardı ve
             yanlış veri yanlış kalıyordu (Yusuf revizyonu 25.08.2026). --}}
        @if (filled($duzeltilebilirAlanlar))
            <section>
                <h2 class="text-base font-semibold">Düzeltilecek bilgiler</h2>

                <div class="mt-4 space-y-5">
                    @foreach ($duzeltilebilirAlanlar as $alan)
                        @php $ad = 'alan['.$alan['girdi'].']'; @endphp

                        <div class="rounded-lg border border-neutral-200 px-4 py-4">
                            <p class="text-sm font-medium text-koyu">{{ $alan['etiket'] }}</p>
                            @if (filled($alan['aciklama']))
                                <p class="mt-0.5 text-xs text-neutral-600">{{ $alan['aciklama'] }}</p>
                            @endif
                            <p class="mt-2 text-xs text-neutral-500">
                                Şu anki değer: <span class="text-neutral-700">{{ $alan['gosterim'] }}</span>
                            </p>

                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @switch($alan['tip'])
                                    @case('il-ilce')
                                        <x-parcalar.il-ilce
                                            :il-ad="'alan['.$alan['girdi'].'_il]'" :ilce-ad="'alan['.$alan['girdi'].'_ilce]'"
                                            :il-yolu="'alan.'.$alan['girdi'].'_il'"
                                            :ilce-yolu="'alan.'.$alan['girdi'].'_ilce'"
                                            :il="$alan['deger']['il'] ?? null" :ilce="$alan['deger']['ilce'] ?? null" />
                                        @break

                                    @case('telefon')
                                        <x-parcalar.telefon :ad="$ad" :etiket="$alan['etiket']"
                                                            :ulke-ad="'alan['.$alan['girdi'].'_ulke]'"
                                                            :yol="'alan.'.$alan['girdi']"
                                                            :deger="$alan['deger']" :sutun="2" />
                                        @break

                                    @case('evet-hayir')
                                        <x-parcalar.evet-hayir :ad="$ad" :etiket="$alan['etiket']"
                                                               :yol="'alan.'.$alan['girdi']"
                                                               :deger="$alan['deger']" zorunlu />
                                        @break

                                    @case('metin-uzun')
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-neutral-800" for="{{ $alan['girdi'] }}">
                                                Yeni {{ mb_strtolower($alan['etiket']) }}
                                            </label>
                                            <textarea id="{{ $alan['girdi'] }}" name="{{ $ad }}" rows="3" required
                                                      class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                                             focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none"
                                            >{{ old('alan.'.$alan['girdi'], is_string($alan['deger']) ? $alan['deger'] : '') }}</textarea>
                                            @error('alan.'.$alan['girdi'])
                                                <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @break

                                    @case('aralik')
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-neutral-800" for="{{ $alan['girdi'] }}">
                                                Yeni {{ mb_strtolower($alan['etiket']) }}
                                            </label>
                                            <select id="{{ $alan['girdi'] }}" name="{{ $ad }}" required
                                                    class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                                           focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
                                                @foreach (App\Enums\CalisanAraligi::cases() as $secenek)
                                                    <option value="{{ $secenek->value }}"
                                                            @selected(old('alan.'.$alan['girdi'], $alan['deger']) === $secenek->value)>
                                                        {{ $secenek->etiket() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('alan.'.$alan['girdi'])
                                                <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @break

                                    @case('sosyal')
                                    @case('platformlar')
                                        {{-- Çok satırlı alanlar: her satır "ad|adres" olarak alınır.
                                             Tekrarlayıcı bir bileşen yerine tek kutu; düzeltme
                                             ekranında nadiren kullanılır, karmaşıklığa değmez. --}}
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-neutral-800" for="{{ $alan['girdi'] }}">
                                                Yeni {{ mb_strtolower($alan['etiket']) }}
                                            </label>
                                            <textarea id="{{ $alan['girdi'] }}" name="{{ $ad }}[]" rows="3"
                                                      placeholder="Her satıra bir adres"
                                                      class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                                             focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none"
                                            >{{ old('alan.'.$alan['girdi'].'.0', is_array($alan['deger']) ? implode("\n", array_filter(array_map(fn ($d) => is_array($d) ? ($d['url'] ?? '') : $d, $alan['deger']))) : '') }}</textarea>
                                            @error('alan.'.$alan['girdi'].'.0')
                                                <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @break

                                    @default
                                        <x-parcalar.alan :ad="$ad" :etiket="'Yeni '.mb_strtolower($alan['etiket'])"
                                                         :yol="'alan.'.$alan['girdi']"
                                                         :tur="$alan['tip'] === 'sayi' ? 'number' : 'text'"
                                                         :deger="is_scalar($alan['deger']) ? $alan['deger'] : null"
                                                         zorunlu />
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Alan listemizde OLMAYAN, yetkilinin elle tanımladığı talepler. --}}
        @if (filled($ekTalepler))
            <section>
                <h2 class="text-base font-semibold">Ek talepler</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($ekTalepler as $ek)
                        @php
                            $ekAd = 'ek['.str_replace(':', '_', $ek['anahtar']).']';
                            $ekHata = $errors->first('ek.'.str_replace(':', '_', $ek['anahtar']));
                            $ekMevcut = $ekYuklenmisler[$ek['etiket']] ?? null;
                        @endphp
                        <div class="rounded-lg border px-4 py-3 {{ $ekHata ? 'border-kulup-600 bg-kulup-50' : 'border-neutral-200' }}">
                            <label class="block text-sm font-medium text-neutral-800" for="{{ $ekAd }}">
                                {{ $ek['etiket'] }}
                            </label>
                            @if (filled($ek['aciklama'] ?? null))
                                <p class="mt-0.5 text-xs text-neutral-600">{{ $ek['aciklama'] }}</p>
                            @endif

                            @if (($ek['tip'] ?? 'dosya') === 'metin')
                                <textarea id="{{ $ekAd }}" name="{{ $ekAd }}" rows="3"
                                          class="mt-2 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                                 focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none"
                                >{{ old('ek.'.str_replace(':', '_', $ek['anahtar'])) }}</textarea>
                            @else
                                @if ($ekMevcut)
                                    <p class="mt-1 text-xs text-neutral-500">
                                        Yüklü dosya: {{ $ekMevcut->orijinal_ad }} — yenisini seçerseniz bunun yerini alır.
                                    </p>
                                @endif
                                <input type="file" id="{{ $ekAd }}" name="{{ $ekAd }}"
                                       class="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border-0
                                              file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
                            @endif

                            @if ($ekHata)<p class="mt-1 text-xs text-kulup-700">{{ $ekHata }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($evrakTurleri->isNotEmpty())
            <section>
                <h2 class="text-base font-semibold">Evraklar</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($evrakTurleri as $tur)
                        @php
                            $hata = $errors->first('evraklar.'.$tur->id);
                            $mevcut = $yuklenmisEvraklar[$tur->id] ?? null;
                        @endphp
                        <div class="rounded-lg border px-4 py-3 {{ $hata ? 'border-kulup-600 bg-kulup-50' : 'border-neutral-200' }}">
                            <label for="evrak-{{ $tur->id }}" class="block text-sm font-medium text-neutral-800">
                                {{ $tur->ad }}
                            </label>
                            @if ($mevcut)
                                <p class="mt-1 text-xs text-neutral-500">
                                    Yüklü dosya: {{ $mevcut->orijinal_ad }} — yenisini seçerseniz bunun yerini alır.
                                </p>
                            @endif
                            <input type="file" id="evrak-{{ $tur->id }}" name="evraklar[{{ $tur->id }}]"
                                   class="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border-0
                                          file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
                            @if ($hata)
                                <p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="text-base font-semibold">Açıklamanız</h2>
            @if (filled($veriNotlari))
                {{-- Düzeltilemeyen işaretler (E-posta, Kurum): bunlar yalnızca
                     burada yanıtlanır, girdi açılmaz. --}}
                <p class="mt-1 text-sm text-neutral-600">
                    Aşağıdaki noktalar için doğrusunu buraya yazın; incelemeyi yapan yetkili görecek.
                </p>
                <ul class="mt-2 space-y-1 text-sm text-neutral-600">
                    @foreach ($veriNotlari as $alan => $aciklama)
                        <li>• <span class="font-medium text-koyu">{{ $basvuru->duzeltmeEtiketi($alan) }}</span>
                            @if (filled($aciklama)) — {{ $aciklama }} @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-1 text-sm text-neutral-600">İsteğe bağlı.</p>
            @endif
                <textarea name="aciklama" rows="4"
                          class="mt-3 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                 focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none"
                >{{ old('aciklama') }}</textarea>
                @error('aciklama')
                    <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
                @enderror
        </section>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="inline-flex items-center rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-medium text-white
                           transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
                Başvurumu yeniden gönder
            </button>
            <span class="text-xs text-neutral-500">
                Bağlantı {{ $bilet->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y') }} tarihine kadar geçerli.
            </span>
        </div>
    </form>
</div>
@endsection
