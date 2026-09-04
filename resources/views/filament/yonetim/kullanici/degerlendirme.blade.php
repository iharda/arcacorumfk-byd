{{-- Kullanıcı detayı · Değerlendirme sekmesi -- Tutarsızlık incelemesi M2.4 md.3.

     🔑 KİŞİ ve KURUM AYRI (akreditasyon detayındaki T12 kuralının aynısı):
     kişi olumlu, çalıştığı kurum sorunlu olabilir. Tek rozet gösterilseydi
     yetkili yanlış kararı verirdi.

     🔒 Sekme yalnızca `degerlendirme.yonet` yetkisi olana çiziliyor
     (KullaniciDetay::sekmeler); puan ve not kişiye hiçbir ekranda görünmez. --}}
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
            <p style="font-size:.85rem; opacity:.7;">Bağlı kurum yok.</p>
        @endif
    </x-filament::section>
</div>
