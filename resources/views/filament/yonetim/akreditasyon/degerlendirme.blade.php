{{-- Akreditasyon detayı · Değerlendirme sekmesi -- T12.

     🔑 KİŞİ ve KURUM AYRI: kişi olumlu, çalıştığı kurum sorunlu olabilir.
     Tek rozet gösterilseydi yetkili yanlış kararı verirdi.

     🔒 Bu sekme yalnızca `degerlendirme.yonet` yetkisi olana çiziliyor
     (sayfa sınıfında); puan ve not kulüp dışına çıkmaz. --}}
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(20rem, 1fr)); gap:1.25rem;">

    <x-filament::section>
        <x-slot name="heading">Kişi</x-slot>
        <x-parcalar.degerlendirme-serit :degerlendirme="$kisi" baslik="Kişi değerlendirmesi" />
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Kurum</x-slot>
        @if ($kurumAdi)
            <p style="font-size:.8rem; opacity:.6; margin:0 0 .5rem;">{{ $kurumAdi }}</p>
            <x-parcalar.degerlendirme-serit :degerlendirme="$kurum" baslik="Kurum değerlendirmesi" />
        @else
            <p style="font-size:.85rem; opacity:.7;">Bağımsız üye — bağlı kurum yok.</p>
        @endif
    </x-filament::section>
</div>
