@extends('layouts.kamu')
@section('baslik', 'Panel seçin')

@section('icerik')
{{-- Bir kişi hem kurum yetkilisi hem basın mensubu olabilir (gazete sahibi
     aynı zamanda muhabir). Giriş ÖNCESİ seçtirmek yanlış seçim ve yeni bir
     hata sınıfı üretirdi; seçim girişten sonra, yalnızca gerektiğinde. --}}
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Hangi panele gireceksiniz?</h1>
    <p class="mt-2 text-sm text-neutral-600">{{ $kullanici->name }} · {{ $kullanici->email }}</p>

    <div class="mt-8 space-y-3">
        @foreach ($paneller as $yol => $ad)
            <a href="{{ $yol }}"
               class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white px-5 py-4 transition hover:border-kulup-600 hover:bg-kulup-50">
                <span class="font-medium">{{ $ad }}</span>
                <span aria-hidden="true" class="text-neutral-400">→</span>
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('cikis') }}" class="mt-8">
        @csrf
        <button type="submit" class="text-sm text-neutral-500 underline hover:text-koyu">Çıkış yap</button>
    </form>
</div>
@endsection
