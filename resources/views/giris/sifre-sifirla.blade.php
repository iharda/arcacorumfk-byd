@extends('layouts.kamu')
@section('baslik', 'Şifre belirleme')

@section('icerik')
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Yeni şifrenizi belirleyin</h1>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('sifre.sifirla') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-parcalar.alan ad="email" etiket="E-posta" tur="email" zorunlu :sutun="2"
                         :deger="$eposta" autocomplete="username" />

        <div>
            <label for="sifre" class="zorunlu block text-sm font-medium text-neutral-800">Yeni şifre</label>
            <input type="password" id="sifre" name="sifre" required autocomplete="new-password"
                   class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
            <p class="mt-1 text-xs text-neutral-500">En az 10 karakter; harf ve rakam içermeli.</p>
        </div>

        <div>
            <label for="sifre_confirmation" class="zorunlu block text-sm font-medium text-neutral-800">Yeni şifre (tekrar)</label>
            <input type="password" id="sifre_confirmation" name="sifre_confirmation" required autocomplete="new-password"
                   class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-xs transition focus:border-kulup-600 focus:ring-2 focus:ring-kulup-600/20 focus:outline-none">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-kulup-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-kulup-700 focus:ring-2 focus:ring-kulup-600/30 focus:outline-none">
            Şifremi kaydet
        </button>
    </form>
</div>
@endsection
