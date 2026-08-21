@php
    use App\Enums\BasvuruTuru;
    $basin = $tur === BasvuruTuru::BasinMensubu;
    $eylem = $davet
        ? route('davet.kaydet', $token)
        : route($basin ? 'basvuru.basin-mensubu.kaydet' : 'basvuru.icerik-ureticisi.kaydet');
@endphp

@extends('layouts.kamu')
@section('baslik', $davet ? 'Başvurunuzu tamamlayın' : $tur->etiket() . ' başvurusu')

@section('icerik')
<div class="mx-auto max-w-3xl">
    @unless ($davet)
        <a href="{{ route('anasayfa') }}" class="text-sm text-neutral-500 hover:text-koyu">← Başvuru türleri</a>
    @endunless

    <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">
        {{ $davet ? 'Başvurunuzu tamamlayın' : $tur->etiket() . ' başvurusu' }}
    </h1>

    @if ($davet)
        {{-- Davet bilgisi: kimin adına ve hangi kurumdan geldiği açıkça yazılmalı. --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm">
            <strong class="font-medium">{{ $davet->kurum?->resmi_unvan }}</strong>
            sizin adınıza başvuru başlattı.
            <span class="text-neutral-600">
                Kayıt: {{ $davet->ad_soyad }} · {{ $davet->eposta }}
            </span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            Formda {{ $errors->count() }} eksik veya hatalı alan var. İşaretli alanları kontrol edin.
        </div>
    @endif

    <form method="POST" action="{{ $eylem }}" class="mt-8 space-y-10">
        @csrf

        <section>
            <h2 class="text-base font-semibold">Kişisel bilgiler</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @unless ($davet)
                    <x-parcalar.alan ad="ad_soyad" etiket="Ad soyad" zorunlu :sutun="2" />
                    <x-parcalar.alan ad="eposta" etiket="E-posta" tur="email" zorunlu :sutun="1" />
                @endunless
                <x-parcalar.alan ad="telefon" etiket="Telefon" tur="tel" zorunlu :sutun="1" ipucu="0500 000 00 00" />
                <x-parcalar.alan ad="adres" etiket="Adres" zorunlu :sutun="2" />
                <x-parcalar.alan ad="il" etiket="İl" zorunlu :sutun="1" />
                <x-parcalar.alan ad="ilce" etiket="İlçe" zorunlu :sutun="1" />
            </div>
        </section>

        @if ($basin)
            <section>
                <h2 class="text-base font-semibold">Kurum ve mesleki bilgiler</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @unless ($davet)
                        @php $kurumHata = $errors->first('kurum_ulid'); @endphp
                        <div class="sm:col-span-2">
                            <label for="kurum_ulid" class="zorunlu block text-sm font-medium text-neutral-800">
                                Çalıştığınız kurum
                            </label>
                            <select id="kurum_ulid" name="kurum_ulid" required
                                    @class([
                                        'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
                                        'border-neutral-300 bg-white' => ! $kurumHata,
                                        'border-kulup-600 bg-kulup-50' => (bool) $kurumHata,
                                    ])>
                                <option value="">Seçiniz…</option>
                                @foreach ($kurumlar as $k)
                                    <option value="{{ $k->ulid }}" @selected(old('kurum_ulid') === $k->ulid)>
                                        {{ $k->resmi_unvan }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($kurumHata)
                                <p class="mt-1 text-xs text-kulup-700">{{ $kurumHata }}</p>
                            @elseif ($kurumlar->isEmpty())
                                <p class="mt-1 text-xs text-neutral-500">
                                    Henüz akredite kurum yok. Kurumunuz önce kendi başvurusunu tamamlamalı.
                                </p>
                            @endif
                        </div>
                    @endunless

                    <x-parcalar.evet-hayir ad="sigorta_212_var" etiket="212 sayılı Basın İş Kanunu sigortası" zorunlu />
                    <x-parcalar.evet-hayir ad="basin_karti_var" etiket="Basın kartı" zorunlu />
                    <x-parcalar.alan ad="calisma_yili" etiket="Medya sektöründe çalışma yılı"
                                     tur="number" zorunlu :sutun="1" min="0" max="70" />
                </div>
            </section>
        @else
            <section>
                <h2 class="text-base font-semibold">Yayın yaptığınız platformlar</h2>
                @error('sosyal_medya')<p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>@enderror
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach (['x' => 'X', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'web' => 'İnternet sitesi / blog'] as $anahtar => $etiket)
                        @php $h = $errors->first("sosyal_medya.$anahtar"); @endphp
                        <div>
                            <label for="sm-{{ $anahtar }}" class="block text-sm font-medium text-neutral-800">{{ $etiket }}</label>
                            <input type="url" id="sm-{{ $anahtar }}" name="sosyal_medya[{{ $anahtar }}]"
                                   value="{{ old("sosyal_medya.$anahtar") }}" placeholder="https://"
                                   @class([
                                       'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
                                       'border-neutral-300 bg-white' => ! $h,
                                       'border-kulup-600 bg-kulup-50' => (bool) $h,
                                   ])>
                            @if($h)<p class="mt-1 text-xs text-kulup-700">{{ $h }}</p>@endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 max-w-xs">
                    <x-parcalar.evet-hayir ad="basin_karti_var" etiket="Basın kartı" zorunlu />
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-base font-semibold">Kişisel verilerin korunması</h2>
            <div class="mt-4 space-y-3">
                @foreach ([
                    'kvkk_aydinlatma' => 'Aydınlatma metnini okudum.',
                    'kvkk_riza'       => 'Başvurumun değerlendirilmesi ve kimlik doğrulaması için kişisel verilerimin işlenmesine açık rıza veriyorum.',
                ] as $ad => $metin)
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="{{ $ad }}" value="1" @checked(old($ad))
                               class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-kulup-600 focus:ring-kulup-600/30">
                        <span class="{{ $errors->has($ad) ? 'text-kulup-700' : 'text-neutral-700' }}">{{ $metin }}</span>
                    </label>
                    @error($ad)<p class="ms-7 text-xs text-kulup-700">{{ $message }}</p>@enderror
                @endforeach
            </div>
        </section>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
                Başvuruyu gönder
            </button>
            <span class="text-sm text-neutral-500">Fotoğraf ve kimlik yükleme sonraki adımda.</span>
        </div>
    </form>
</div>
@endsection
