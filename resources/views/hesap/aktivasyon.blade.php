@extends('layouts.kamu')
@section('baslik', 'Şifrenizi belirleyin')

@section('icerik')
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Şifrenizi belirleyin</h1>
    <p class="mt-2 text-sm text-neutral-600">
        <strong class="font-medium text-koyu">{{ $kullanici->email }}</strong> için bir şifre oluşturun.
    </p>

    <form method="POST" action="{{ $imzaliAdres }}" class="mt-8 space-y-4">
        @csrf
        <x-parcalar.alan ad="sifre" etiket="Şifre" tur="password" zorunlu :sutun="1"
                         autocomplete="new-password" ipucu="En az 10 karakter, harf ve rakam" />
        <x-parcalar.alan ad="sifre_confirmation" etiket="Şifre (tekrar)" tur="password" zorunlu :sutun="1"
                         autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
            Şifreyi kaydet ve devam et
        </button>
    </form>
</div>
@endsection
