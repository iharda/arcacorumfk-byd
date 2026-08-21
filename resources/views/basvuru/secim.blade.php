@extends('layouts.kamu')
@section('baslik', 'Akreditasyon başvurusu')

@section('icerik')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Basın akreditasyon başvurusu</h1>
    <p class="mt-3 text-neutral-600">
        Kulüp etkinliklerine ve maçlarına akredite basın olarak katılmak için başvuru türünüzü seçin.
        Tüm başvurular kulüp yetkilisinin incelemesinden geçer.
    </p>

    <div class="mt-8 grid gap-4">
        <a href="{{ route('basvuru.kurum') }}"
           class="group flex items-start gap-4 rounded-xl border border-neutral-200 bg-white p-5 transition hover:border-kulup-600 hover:shadow-sm">
            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-kulup-50 text-kulup-700">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M3 3.5A1.5 1.5 0 0 1 4.5 2h6A1.5 1.5 0 0 1 12 3.5V18H3V3.5Zm10.5 4H16A1.5 1.5 0 0 1 17.5 9v9h-4V7.5ZM5.5 5.5h3v2h-3v-2Zm0 4h3v2h-3v-2Zm0 4h3v2h-3v-2Z"/>
                </svg>
            </span>
            <span class="flex-1">
                <span class="block font-semibold">Medya kuruluşu</span>
                <span class="mt-1 block text-sm text-neutral-600">
                    Gazete, ajans, televizyon veya dijital yayın kuruluşu adına başvuru.
                    Çalışanlarınızın başvurabilmesi için önce kurumunuzun akredite olması gerekir.
                </span>
            </span>
            <span class="mt-1 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-kulup-600" aria-hidden="true">→</span>
        </a>

        @foreach ([
            ['Basın mensubu', 'Akredite bir medya kuruluşunun çalışanı olarak bireysel başvuru.'],
            ['İçerik üreticisi', 'Bağımsız gazeteci ve içerik üreticileri için başvuru.'],
        ] as [$ad, $aciklama])
            <div class="flex items-start gap-4 rounded-xl border border-dashed border-neutral-300 bg-neutral-100/60 p-5">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-neutral-400">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-7 7.5c0-3.038 3.134-5.5 7-5.5s7 2.462 7 5.5H3Z"/>
                    </svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-neutral-500">{{ $ad }}</span>
                    <span class="mt-1 block text-sm text-neutral-500">{{ $aciklama }}</span>
                </span>
                <span class="mt-0.5 rounded-full bg-neutral-200 px-2.5 py-1 text-[0.7rem] font-medium text-neutral-600">
                    Yakında
                </span>
            </div>
        @endforeach
    </div>
</div>
@endsection
