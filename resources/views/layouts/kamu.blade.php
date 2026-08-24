<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Başvuru sayfaları arama motorunda çıkmasın (sistem henüz canlı değil) --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('baslik', 'Basın Yönetim Sistemi') · ARCA Çorum FK</title>
    <link rel="icon" href="{{ asset('marka/favicon-64.png') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('marka/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-neutral-50 font-sans text-koyu antialiased">

<header class="border-b border-neutral-200 bg-white">
    <div class="mx-auto flex max-w-5xl items-center gap-4 px-5 py-4">
        <a href="{{ route('anasayfa') }}" class="flex items-center gap-3">
            <img src="{{ asset('marka/kulup-logo.webp') }}" alt="ARCA Çorum FK" class="h-11 w-11" width="44" height="44">
            <span class="leading-tight">
                <span class="block text-[0.95rem] font-semibold">ARCA Çorum FK</span>
                <span class="block text-xs text-neutral-500">Basın Yönetim Sistemi</span>
            </span>
        </a>
        {{-- Tek giriş kapısı (Revizyon md.4): "hangi girişten gireceğim"
             sorusu ortadan kalktı. --}}
        <nav class="ms-auto flex items-center gap-1 text-sm">
            <a href="{{ route('giris') }}" class="rounded-md px-3 py-2 text-neutral-600 transition hover:bg-neutral-100 hover:text-koyu">
                Giriş
            </a>
        </nav>
    </div>
</header>

<main class="mx-auto w-full max-w-5xl flex-1 px-5 py-10">
    @yield('icerik')
</main>

<footer class="border-t border-neutral-200 bg-white">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-5 py-6 text-xs text-neutral-500">
        <span>© {{ date('Y') }} ARCA Çorum FK</span>
        <a href="{{ route('hukuki.metin', 'aydinlatma') }}" class="hover:text-koyu">Aydınlatma metni</a>
        <a href="{{ route('hukuki.metin', 'acik-riza') }}" class="hover:text-koyu">Açık rıza</a>
        <a href="{{ route('hukuki.metin', 'gizlilik') }}" class="hover:text-koyu">Gizlilik</a>
        <span class="ms-auto">Basın akreditasyon ve giriş yönetimi</span>
    </div>
</footer>

</body>
</html>
