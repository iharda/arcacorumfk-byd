{{-- Panel markası: arma + sistem adı.

     🪤 SATIR İÇİ STİL BİLEREK: panel Filament'in DERLENMİŞ CSS'ini yükler,
     bizim yazdığımız Tailwind sınıfları (h-full, gap-2.5, text-[0.9rem]...)
     o pakette YOKTUR — sessizce çalışmaz ve arma sayfayı dağıtır.
     Aynı tuzağa ValCert'te de düşmüştük.

     ⚠️ Dış kutu SABİT yükseklikte (panelde brandLogoHeight). Bu blok o
     yüksekliği aşarsa alttaki başlığın üstüne biner. --}}
<div style="display:flex; align-items:center; gap:.6rem; height:100%;">
    <img
        src="{{ asset('marka/kulup-logo.webp') }}"
        alt="ARCA Çorum FK"
        style="height:100%; width:auto; flex:none; display:block;"
    >
    <span style="display:flex; flex-direction:column; justify-content:center; line-height:1.15; text-align:start;">
        <span style="font-size:.9rem; font-weight:600;">ARCA Çorum FK</span>
        <span style="font-size:.72rem; opacity:.62;">{{ $altBaslik ?? 'Basın Yönetim Sistemi' }}</span>
    </span>
</div>
