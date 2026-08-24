@extends('layouts.kamu')
@section('baslik', 'Başvurunuz alındı')

@section('icerik')
<div class="mx-auto max-w-xl text-center">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-kulup-50 text-kulup-700">
        <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M2.5 5.5A1.5 1.5 0 0 1 4 4h12a1.5 1.5 0 0 1 1.5 1.5v.3l-7.5 4.2-7.5-4.2v-.3Zm0 2.3V14.5A1.5 1.5 0 0 0 4 16h12a1.5 1.5 0 0 0 1.5-1.5V7.8l-7.13 3.99a.75.75 0 0 1-.74 0L2.5 7.8Z"/>
        </svg>
    </div>

    <h1 class="mt-5 text-2xl font-semibold tracking-tight">
        {{ $duzeltme ? 'Düzeltmeniz alındı' : 'Başvurunuz kaydedildi' }}
    </h1>
    @if ($duzeltme)
        <p class="mt-3 text-neutral-600">
            Başvurunuz yeniden incelemeye alındı. Sonuç
            <strong class="font-medium text-koyu">{{ $eposta }}</strong> adresine bildirilecek.
        </p>
        <p class="mt-2 text-sm text-neutral-500">Kullandığınız düzeltme bağlantısı artık geçerli değil.</p>
    @else
        {{-- Hesap ONAY anında açılır (Revizyon md.1): başvuranın yapacağı
             başka bir adım YOK, bekleyecek. --}}
        <p class="mt-3 text-neutral-600">
            Başvurunuz evraklarıyla birlikte inceleme kuyruğuna alındı. Sonuç
            <strong class="font-medium text-koyu">{{ $eposta }}</strong> adresine bildirilecek.
        </p>
    @endif

    <a href="{{ route('anasayfa') }}"
       class="mt-8 inline-flex items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-neutral-50">
        Başa dön
    </a>
</div>
@endsection
