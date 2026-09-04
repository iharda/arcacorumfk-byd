@extends('layouts.kamu')
@section('baslik', 'Çok fazla deneme')

@section('icerik')
@php
    // ThrottleRequests `Retry-After` başlığını saniye olarak koyar. Kullanıcıya
    // saniye değil dakika söylenir; "37 saniye" bekleyecek kişi sayfayı yeniler.
    $saniye = (int) ($exception?->getHeaders()['Retry-After'] ?? 0);
    $dakika = max(1, (int) ceil($saniye / 60));
@endphp

<div class="mx-auto max-w-xl text-center">
    <h1 class="text-2xl font-semibold tracking-tight">Çok fazla deneme yaptınız</h1>

    <p class="mt-3 text-neutral-600">
        Güvenlik gereği kısa sürede yapılan gönderim sayısı sınırlı.
        Yaklaşık <strong class="font-medium text-koyu">{{ $dakika }} dakika</strong>
        sonra tekrar deneyebilirsiniz.
    </p>

    {{-- 🔑 Çıkmaz sokak bırakma: kullanıcı formunun silindiğini sanıp baştan
         başlamasın. Tarayıcı geri tuşu alanları dolu getirir. --}}
    <p class="mt-3 text-neutral-600">
        Doldurduğunuz bilgiler kaybolmadı. Tarayıcınızın geri tuşuyla forma
        dönüp bekleme süresi bittikten sonra gönderebilirsiniz.
    </p>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <button type="button" onclick="history.back()"
                class="inline-flex items-center rounded-lg bg-kulup-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kulup-700">
            Forma dön
        </button>
        <a href="{{ route('anasayfa') }}"
           class="inline-flex items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-neutral-50">
            Başa dön
        </a>
    </div>
</div>
@endsection
