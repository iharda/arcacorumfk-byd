@extends('layouts.kamu')
@section('baslik', 'Şifremi unuttum')

@section('icerik')
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Şifremi unuttum</h1>

    @if (session('bilgi'))
        <div class="mt-6 rounded-lg border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-700">
            {{ session('bilgi') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('sifre.istek.gonder') }}" class="mt-8 space-y-5">
        @csrf
        <x-parcalar.alan ad="email" etiket="E-posta" tur="email" zorunlu :sutun="2" autocomplete="username" />

        <button type="submit"
                class="w-full rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
            Bağlantı gönder
        </button>
    </form>

    <a href="{{ route('giris') }}" class="mt-6 inline-block text-sm text-neutral-600 underline hover:text-koyu">← Giriş sayfası</a>
</div>
@endsection
