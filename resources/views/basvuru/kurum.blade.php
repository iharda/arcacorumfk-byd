@extends('layouts.kamu')
@section('baslik', 'Medya kuruluşu başvurusu')

@section('icerik')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('anasayfa') }}" class="text-sm text-neutral-500 hover:text-koyu">← Başvuru türleri</a>
    <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Medya kuruluşu başvurusu</h1>

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

    <form method="POST" action="{{ route('basvuru.kurum.kaydet') }}" enctype="multipart/form-data"
          class="mt-8 space-y-10"
          x-data="{
              platformlar: {{ json_encode(old('yayin_platformlari', [['ad' => '', 'url' => '']])) }},
              ekle() { this.platformlar.push({ ad: '', url: '' }) },
              cikar(i) { if (this.platformlar.length > 1) this.platformlar.splice(i, 1) }
          }">
        @csrf

        {{-- ── Kurum bilgileri ─────────────────────────────── --}}
        <section>
            <h2 class="text-base font-semibold">Kurum bilgileri</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-parcalar.alan ad="resmi_unvan" etiket="Resmi unvan" zorunlu :sutun="2" />
                <x-parcalar.alan ad="adres" etiket="Adres" zorunlu :sutun="2" />
                <x-parcalar.il-ilce />
                <x-parcalar.telefon ad="kurum_telefon" etiket="Telefon" ipucu="364 213 45 67" />
                <x-parcalar.alan ad="kurum_eposta" etiket="E-posta" tur="email" zorunlu :sutun="1" />
                <x-parcalar.alan ad="vergi_dairesi" etiket="Vergi dairesi" zorunlu :sutun="1" />
                <x-parcalar.alan ad="vergi_no" etiket="Vergi numarası" zorunlu :sutun="1"
                                 ipucu="10 haneli vergi no veya 11 haneli T.C. kimlik no" inputmode="numeric" />

                @php $calisanHata = $errors->first('calisan_araligi'); @endphp
                <div>
                    <label for="calisan_araligi" class="zorunlu block text-sm font-medium text-neutral-800">Çalışan sayısı</label>
                    <select id="calisan_araligi" name="calisan_araligi" required
                            @class([
                                'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
                                'border-neutral-300 bg-white' => ! $calisanHata,
                                'border-kulup-600 bg-kulup-50' => (bool) $calisanHata,
                            ])>
                        <option value="">Seçiniz…</option>
                        @foreach (App\Enums\CalisanAraligi::secenekler() as $deger => $etiket)
                            <option value="{{ $deger }}" @selected(old('calisan_araligi') === $deger)>{{ $etiket }}</option>
                        @endforeach
                    </select>
                    @if ($calisanHata)<p class="mt-1 text-xs text-kulup-700">{{ $calisanHata }}</p>@endif
                </div>
            </div>
        </section>

        {{-- ── Yayın platformları (tekrarlanabilir) ────────── --}}
        <section>
            <h2 class="text-base font-semibold">Web siteleri ve yayın adresleri</h2>
            @error('yayin_platformlari')
                <p class="mt-1 text-xs text-kulup-700">{{ $message }}</p>
            @enderror

            <div class="mt-4 space-y-3">
                <template x-for="(p, i) in platformlar" :key="i">
                    <div class="flex gap-3">
                        <input type="text" :name="`yayin_platformlari[${i}][ad]`" x-model="p.ad"
                               placeholder="Örn. Çorum Haber Gazetesi" required
                               class="w-1/3 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
                        <input type="url" :name="`yayin_platformlari[${i}][url]`" x-model="p.url"
                               placeholder="https://ornek.com.tr" required
                               class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
                        <button type="button" @click="cikar(i)" x-show="platformlar.length > 1"
                                class="rounded-lg border border-neutral-300 px-3 text-sm text-neutral-500 transition hover:border-kulup-600 hover:text-kulup-700"
                                aria-label="Satırı kaldır">×</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="ekle()"
                    class="mt-3 rounded-lg border border-dashed border-neutral-300 px-3 py-1.5 text-sm text-neutral-600 transition hover:border-kulup-600 hover:text-kulup-700">
                + Adres ekle
            </button>
        </section>

        {{-- ── Sosyal medya ────────────────────────────────── --}}
        <section>
            <h2 class="text-base font-semibold">Sosyal medya hesapları</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach (['x' => 'X', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $anahtar => $etiket)
                    @php $hata = $errors->first("sosyal_medya.$anahtar"); @endphp
                    <div>
                        <label for="sm-{{ $anahtar }}" class="block text-sm font-medium text-neutral-800">{{ $etiket }}</label>
                        <input type="url" id="sm-{{ $anahtar }}" name="sosyal_medya[{{ $anahtar }}]"
                               value="{{ old("sosyal_medya.$anahtar") }}" placeholder="https://"
                               @class([
                                   'mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none',
                                   'border-neutral-300 bg-white' => ! $hata,
                                   'border-kulup-600 bg-kulup-50' => (bool) $hata,
                               ])>
                        @if($hata)<p class="mt-1 text-xs text-kulup-700">{{ $hata }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Yetkili kişi ────────────────────────────────── --}}
        <section>
            <h2 class="text-base font-semibold">Başvuruyu yapan yetkili</h2>
            <p class="mt-1 text-sm text-neutral-600">Başvuru onaylanırsa hesap bu kişiye açılır; çalışan başvuruları buradan yürütülür.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-parcalar.alan ad="yetkili_ad" etiket="Ad soyad" zorunlu :sutun="2" />
                <x-parcalar.alan ad="yetkili_eposta" etiket="E-posta" tur="email" zorunlu :sutun="1" />
                <x-parcalar.telefon ad="yetkili_telefon" etiket="Cep telefonu" />
            </div>
        </section>

        {{-- ── Evraklar (Revizyon md.3.1: başvuruyla AYNI adımda) ── --}}
        <x-parcalar.evraklar :turler="$evrakTurleri" />

        {{-- ── KVKK ────────────────────────────────────────── --}}
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
            <button type="submit"
                    class="rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
                Başvuruyu gönder
            </button>
        </div>
    </form>
</div>
@endsection
