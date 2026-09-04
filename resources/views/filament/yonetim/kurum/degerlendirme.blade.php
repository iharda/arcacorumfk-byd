{{-- Kurum detayı · Değerlendirme sekmesi -- Tutarsızlık incelemesi M2.4 md.2.

     Kurumlar listesinde yalnızca PUAN rozeti görünüyordu; notu okumanın yolu
     yoktu (M3 №5). Karar veren yetkili puanı değil gerekçesini arıyor.

     🔒 Sekme yalnızca `degerlendirme.yonet` yetkisi olana çiziliyor
     (KurumDetay::sekmeler); puan ve not kulüp dışına çıkmaz. --}}
<x-filament::section>
    <x-slot name="heading">Değerlendirme</x-slot>

    <p style="font-size:.8rem; opacity:.6; margin:0 0 .5rem;">{{ $kurumAdi }}</p>

    <x-parcalar.degerlendirme-serit
        :degerlendirme="$degerlendirme"
        baslik="Kurum değerlendirmesi" />

    {{-- Puanlama düğmesi ŞERİDİN YANINDA: eskiden sayfanın sağ üstündeydi,
         yetkili puanı okuduğu yerden değil ekranın öbür ucundan puanlıyordu. --}}
    <div style="margin-top:.9rem;">{{ $this->degerlendirAction }}</div>
</x-filament::section>
