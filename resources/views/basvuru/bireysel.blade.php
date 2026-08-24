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
            {{-- Alana bağlanamayan engel (ör. hesap durumu) burada YAZIYLA görünmeli;
                 yalnızca sayı göstermek kullanıcıyı çıkmazda bırakır. --}}
            @if ($errors->has('genel'))
                {{ $errors->first('genel') }}
            @else
                Formda {{ $errors->count() }} eksik veya hatalı alan var. İşaretli alanları kontrol edin.
            @endif
        </div>
    @endif

    {{-- kurum: seçili kurum ULID'i ya da "yok" (kurumu listede olmayan aday). --}}
    <form method="POST" action="{{ $eylem }}" enctype="multipart/form-data" class="mt-8 space-y-10"
          x-data="{ kurum: @js(old('kurum_ulid', ($basin && ! $davet && $kurumlar->isEmpty()) ? 'yok' : '')) }">
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
                            <select id="kurum_ulid" name="kurum_ulid" required x-model="kurum"
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
                                {{-- Listede yalnızca AKREDİTE kurumlar var; kalan herkes buraya düşer. --}}
                                <option value="yok" @selected(old('kurum_ulid') === 'yok' || $kurumlar->isEmpty())>
                                    Kurumum listede yok
                                </option>
                            </select>
                            @if ($kurumHata)
                                <p class="mt-1 text-xs text-kulup-700">{{ $kurumHata }}</p>
                            @endif

                            {{--
                              Çıkmaz sokak bırakma: kurumu listede olmayan aday eskiden
                              boş bir açılır listeye bakıp kalıyordu. Yapması gereken şey
                              ve düğmesi burada; yalnızca "listede yok" seçilince görünür.
                            --}}
                            <div x-cloak x-show="kurum === 'yok'"
                                 class="mt-3 rounded-lg border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm">
                                <p class="text-neutral-700">
                                    Basın mensubu akreditasyonu, çalıştığınız kurumun akredite olmasına bağlı.
                                    Kurumunuz başvurdu ve sonuç bekliyorsa akreditasyon tamamlanınca bu listede
                                    görünecek. Henüz başvurmadıysa önce kurum başvurusu yapılmalı.
                                    Kurumunuz akredite olduktan sonra sizi doğrudan davet edebilir; o zaman bu
                                    formu baştan doldurmanız gerekmez.
                                </p>
                                {{-- 🪤 YENİ SEKME: aynı sekmede açılırsa yarım kalan form ve
                                     kişinin yazdığı her şey kaybolur. --}}
                                <a href="{{ route('basvuru.kurum') }}" target="_blank" rel="noopener"
                                   class="mt-3 inline-block rounded-lg bg-kulup-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-kulup-700">
                                    Kurum başvurusu yap
                                </a>
                            </div>
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

        {{-- ── Evraklar (Revizyon md.3.1: başvuruyla AYNI adımda) ── --}}
        <x-parcalar.evraklar :turler="$evrakTurleri" />

        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-base font-semibold">Kişisel verilerin korunması</h2>
            <div class="mt-4 space-y-3">
                @foreach ([
                    'kvkk_aydinlatma' => 'aydinlatma',
                    'kvkk_riza'       => 'riza',
                ] as $ad => $tur)
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="{{ $ad }}" value="1" @checked(old($ad))
                               class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-kulup-600 focus:ring-kulup-600/30">
                        <span class="{{ $errors->has($ad) ? 'text-kulup-700' : 'text-neutral-700' }}">
                            @if ($tur === 'aydinlatma')
                                <a href="{{ route('hukuki.metin', 'aydinlatma') }}" target="_blank" rel="noopener"
                                   class="font-medium text-kulup-700 underline">Aydınlatma metnini</a> okudum.
                            @else
                                Başvurumun değerlendirilmesi için kişisel verilerimin işlenmesine
                                <a href="{{ route('hukuki.metin', 'acik-riza') }}" target="_blank" rel="noopener"
                                   class="font-medium text-kulup-700 underline">açık rıza</a> veriyorum.
                            @endif
                        </span>
                    </label>
                    @error($ad)<p class="ms-7 text-xs text-kulup-700">{{ $message }}</p>@enderror
                @endforeach
            </div>
        </section>

        <div class="flex items-center gap-4">
            <button type="submit" x-bind:disabled="kurum === 'yok'"
                    class="rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-500 disabled:hover:bg-neutral-300">
                Başvuruyu gönder
            </button>
        </div>
    </form>
</div>
@endsection
