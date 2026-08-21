@extends('layouts.kamu')
@section('baslik', $baslik)

@section('icerik')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $baslik }}</h1>

    @if (filled($icerik))
        @if ($guncelleme)
            <p class="mt-2 text-sm text-neutral-500">
                Son güncelleme: {{ \Illuminate\Support\Carbon::parse($guncelleme)->timezone('Europe/Istanbul')->format('d.m.Y') }}
            </p>
        @endif

        {{-- Metin yönetim panelinden zengin metin olarak girilir. --}}
        <div class="prose prose-neutral mt-8 max-w-none text-[0.95rem] leading-relaxed
                    prose-headings:font-semibold prose-a:text-kulup-700">
            {!! $icerik !!}
        </div>
    @else
        {{-- 🚨 Metin yoksa BOŞ SAYFA GÖSTERME. Kullanıcı "okudum" diye
             işaretlediği şeyin ne olduğunu bilmeli. --}}
        <div class="mt-8 rounded-xl border border-kulup-600 bg-kulup-50 px-5 py-4">
            <p class="text-sm font-medium text-kulup-800">Bu metin henüz yayımlanmadı.</p>
            <p class="mt-2 text-sm text-kulup-800/80">
                Metin kulüp tarafından hazırlanıp sisteme girildiğinde burada yayımlanacaktır.
                Bu süre zarfında kişisel verileriniz yalnızca akreditasyon başvurunuzun
                değerlendirilmesi amacıyla işlenir ve üçüncü kişilerle paylaşılmaz.
            </p>
        </div>
    @endif

    <a href="{{ route('anasayfa') }}"
       class="mt-10 inline-flex items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-neutral-50">
        Başa dön
    </a>
</div>
@endsection
