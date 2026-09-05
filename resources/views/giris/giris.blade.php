@extends('layouts.kamu')
@section('baslik', 'Giriş')

@section('icerik')
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Giriş</h1>

    @if (session('bilgi'))
        <div class="mt-6 rounded-lg border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-700">
            {{ session('bilgi') }}
        </div>
    @endif

    @if (! empty($uyari))
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            {{ $uyari }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('giris.yap') }}" class="mt-8 space-y-5">
        @csrf

        <x-parcalar.alan ad="email" etiket="E-posta" tur="email" zorunlu :sutun="2" autocomplete="username" />

        <div>
            <label for="password" class="zorunlu block text-sm font-medium text-neutral-800">Şifre</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-neutral-700">
                <input type="checkbox" name="hatirla" value="1" @checked(old('hatirla'))
                       class="h-4 w-4 rounded border-neutral-300 text-kulup-600 focus:ring-kulup-600/30">
                Beni hatırla
            </label>
            <a href="{{ route('sifre.istek') }}" class="text-sm font-medium text-kulup-700 underline">Şifremi unuttum</a>
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
            Giriş yap
        </button>
    </form>

    {{-- 💀 KULÜP YETKİLİSİ UYARISI BURADA, DÜĞMENİN ALTINDA (İbrahim Bey,
         05.09.2026). Aynı bağlantı sayfanın en dibinde gri ve küçük duruyordu;
         kimse görmüyordu. Yetkili e-postasını VE şifresini buraya yazıyor,
         sistem şifreyi doğruladıktan SONRA onu yönetim girişine atıyordu --
         uyarı hem çok geç geliyor hem de köşede 6 saniyede kayboluyordu.
         Doğru zaman: kişi daha hiçbir şey yazmadan önce. --}}
    <p class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-center text-sm text-neutral-700">
        Kulüp yetkilisi misiniz?
        <a href="{{ route('filament.yonetim.auth.login') }}"
           class="font-semibold text-kulup-700 underline hover:text-kulup-800">Yönetim girişi ayrıdır</a>
        <span class="mt-0.5 block text-xs text-neutral-500">İki adımlı doğrulama orada zorunludur.</span>
    </p>

    {{-- Kulüp yetkilisinin kapısı ayrı (iki adımlı doğrulama orada zorunlu);
         başvurusu olmayan da buradan başvuruya geçebilsin. --}}
    {{-- 🔑 Reddedilen adayın hesabı HİÇ AÇILMAZ; buradan giremez ve "E-posta
         veya şifre hatalı" cümlesi ona cevap vermez. Hesabın var olup
         olmadığını sızdırmadan cevabı bulabileceği yer burası. --}}
    <p class="mt-6 text-center text-sm text-neutral-600">
        Giriş yapamıyor musunuz?
        <a href="{{ route('basvuru.durum') }}" class="font-medium text-kulup-700 hover:underline">
            Başvurunuzun durumunu sorgulayın
        </a>
    </p>

    {{-- Yetkili bağlantısı buradan YUKARI taşındı: iki yerde durunca ikisi de
         sıradanlaşıyordu. Burada yalnız başvuru yolu kalıyor. --}}
    <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-5 text-sm text-neutral-600">
        <a href="{{ route('anasayfa') }}" class="hover:text-koyu">Akreditasyon başvurusu yapın</a>
    </div>
</div>
@endsection
