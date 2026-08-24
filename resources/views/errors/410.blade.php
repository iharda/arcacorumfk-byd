@extends('layouts.kamu')
@section('baslik', 'Bağlantı geçerli değil')

@section('icerik')
<div class="mx-auto max-w-xl text-center">
    <h1 class="text-2xl font-semibold tracking-tight">Bu bağlantı artık geçerli değil</h1>

    {{-- Sebep controller'dan gelir (süresi doldu / kullanıldı / düzeltme beklenmiyor).
         Boşsa genel metin gösterilir; kullanıcı ne yapacağını her hâlükârda bilmeli. --}}
    <p class="mt-3 text-neutral-600">
        {{ $exception?->getMessage() ?: 'Bağlantının süresi dolmuş ya da daha önce kullanılmış olabilir.' }}
    </p>

    <a href="{{ route('anasayfa') }}"
       class="mt-8 inline-flex items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-neutral-50">
        Başa dön
    </a>
</div>
@endsection
