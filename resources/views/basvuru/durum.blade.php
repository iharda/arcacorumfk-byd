@extends('layouts.kamu')
@section('baslik', 'Başvuru durumu')

@section('icerik')
<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Başvuru durumu</h1>

    <p class="mt-2 text-sm text-neutral-600">
        Başvuru numaranız, başvurunuz alındığında gönderdiğimiz e-postada yazıyor.
    </p>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- 🔒 Bulunamadı hâlinde TEK cümle: "numara doğru ama e-posta yanlış"
         gibi bir ayrım, numarayı bilen birine adresi doğrulatırdı. --}}
    @if (($sorgulandi ?? false) && ! $basvuru)
        <div class="mt-6 rounded-lg border border-kulup-600 bg-kulup-50 px-4 py-3 text-sm text-kulup-800">
            Bu başvuru numarası ve e-posta ile eşleşen bir kayıt bulunamadı.
            Bilgileri e-postanızdaki gibi yazdığınızdan emin olun.
        </div>
    @endif

    @if ($sorgulandi ?? false)
        @if ($basvuru)
            @php
                $durum = $basvuru->durum;
                $reddedildi = $durum === \App\Enums\BasvuruDurumu::Reddedildi;
            @endphp

            <div class="mt-6 rounded-lg border border-neutral-300 bg-white px-4 py-4">
                <div class="text-xs uppercase tracking-wide text-neutral-500">
                    {{ $basvuru->basvuru_no }}
                </div>

                <div class="mt-1 text-lg font-semibold text-neutral-900">
                    {{ $basvuru->durumEtiketi() }}
                </div>

                <p class="mt-2 text-sm text-neutral-700">{{ $durum->aciklama() }}</p>

                <dl class="mt-4 space-y-1 text-sm text-neutral-700">
                    @if ($basvuru->gonderildi_at)
                        <div class="flex gap-2">
                            <dt class="text-neutral-500">Gönderim:</dt>
                            <dd>{{ $basvuru->gonderildi_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</dd>
                        </div>
                    @endif
                    @if ($basvuru->karar_at)
                        <div class="flex gap-2">
                            <dt class="text-neutral-500">Sonuç:</dt>
                            <dd>{{ $basvuru->karar_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Red gerekçesi başvurana AYNEN iletilir (inceleme ekranındaki
                     alanın yardım metni bunu söylüyor); burada da okunabilsin. --}}
                @if ($reddedildi && filled($basvuru->karar_gerekcesi))
                    <p class="mt-4 rounded-lg bg-neutral-50 px-3 py-2 text-sm text-neutral-700">
                        {{ $basvuru->karar_gerekcesi }}
                    </p>
                @endif

                {{-- 🔑 Reddedilen kişi yeniden başvurabilir: kural
                     BasvuruUygunlugu'nda da böyle. Çıkmaz sokak bırakmıyoruz. --}}
                @if ($reddedildi)
                    <a href="{{ route('anasayfa') }}"
                       class="mt-4 inline-flex rounded-lg bg-kulup-600 px-4 py-2 text-sm font-medium text-white">
                        Yeniden başvur
                    </a>
                @endif
            </div>
        @endif
    @endif

    <form method="POST" action="{{ route('basvuru.durum.sorgula') }}" class="mt-8 space-y-5">
        @csrf

        <x-parcalar.alan ad="basvuru_no" etiket="Başvuru numarası" zorunlu :sutun="2" ipucu="2026-BV-0031" />
        <x-parcalar.alan ad="eposta" etiket="E-posta" tur="email" zorunlu :sutun="2" />

        <button type="submit"
                class="w-full rounded-lg bg-kulup-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-kulup-700">
            Durumu sorgula
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">
        <a href="{{ route('giris') }}" class="font-medium text-kulup-700 hover:underline">Giriş ekranına dön</a>
    </p>
</div>
@endsection
