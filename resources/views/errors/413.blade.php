@extends('layouts.kamu')
@section('baslik', 'Dosyalar çok büyük')

@section('icerik')
<div class="mx-auto max-w-xl text-center">
    <h1 class="text-2xl font-semibold tracking-tight">Gönderdiğiniz dosyalar çok büyük</h1>

    {{-- 🪤 Bu hata alan bazlı doğrulamadan ÖNCE oluşur: sunucu isteğin tamamını
         hiç okumaz, bu yüzden "hangi dosya" bilgisi yoktur. Kullanıcıya
         yapabileceği tek şey söylenir. --}}
    <p class="mt-3 text-neutral-600">
        Başvuru formundaki dosyaların toplamı sunucu sınırını aştı.
        Dosyaları küçültüp yeniden deneyin; her evrak için sınır kutusunun altında yazıyor.
    </p>

    <a href="{{ url()->previous(route('anasayfa')) }}"
       class="mt-8 inline-flex items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-neutral-50">
        Forma dön
    </a>
</div>
@endsection
