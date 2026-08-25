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
        <h2 class="text-sm font-semibold">Düzeltilmesi istenenler</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($basvuru->duzeltme_notlari ?? [] as $alan => $aciklama)
                <li>
                    <span class="font-medium text-koyu">{{ $basvuru->duzeltmeEtiketi($alan) }}</span>
                    <span class="text-neutral-600">— {{ $aciklama }}</span>
                </li>
            @endforeach
        </ul>

        @if (filled($basvuru->karar_gerekcesi))
            <p class="mt-3 border-t border-neutral-200 pt-3 text-sm text-neutral-600">
                {{ $basvuru->karar_gerekcesi }}
            </p>
        @endif
    </section>

    <form method="POST" action="{{ route('basvuru.duzelt.kaydet', ['token' => $token]) }}"
          enctype="multipart/form-data" class="mt-8 space-y-8">
        @csrf

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

        @if (filled($veriNotlari))
            <section>
                <h2 class="text-base font-semibold">Açıklamanız</h2>
                <p class="mt-1 text-sm text-neutral-600">
                    Yukarıdaki bilgi alanlarının doğrusunu buraya yazın; incelemeyi yapan yetkili görecek.
                </p>
                <textarea name="aciklama" rows="4"
                          class="mt-3 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm
                                 focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none"
                >{{ old('aciklama') }}</textarea>
                @error('aciklama')
                    <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
                @enderror
            </section>
        @endif

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
