<?php

namespace App\Servisler;

use App\Models\Kurum;

/**
 * Kurum akreditasyonunun kapanması ve yeniden açılması.
 *
 * 🔁 İKİ YÖN TEK YERDE. Eylemler tabloya ayrı ayrı yazıldığında yalnız kaldırma
 * yazılmış, geri verme unutulmuştu: eylem durumu 'iptal' yapıp `akrediteMi()`
 * false olunca kendini gizliyor, tersini yapan bir şey de olmadığı için kurum
 * "İptal"de kilitleniyordu -- çalışanları yeni başvuru yapamıyor, tek çıkış
 * veritabanına elle müdahale oluyordu. (Saha notları T6.)
 *
 * ⚠️ Kartlara DOKUNULMAZ. Kaldırma mevcut kartları iptal etmez, geri verme de
 * iptal edilmiş kartları geri getirmez; kart yaşam döngüsü ayrı yürür.
 */
class KurumAkreditasyonu
{
    public function __construct(private DenetimYazici $denetim) {}

    public function kaldir(Kurum $kurum, string $gerekce): void
    {
        $eski = ['akreditasyon_durumu' => $kurum->akreditasyon_durumu];

        $kurum->update(['akreditasyon_durumu' => 'iptal']);

        $this->denetim->yaz('kurum.akreditasyon_kaldirildi', $kurum,
            eski: $eski,
            yeni: ['akreditasyon_durumu' => 'iptal'],
            not: $gerekce);
    }

    /**
     * İptal edilmiş kurumu yeniden akredite eder.
     *
     * Kontenjan burada sorulur çünkü başvuru kabulünü doğrudan etkiliyor ve
     * değiştirilecek başka ekranı yok; null = sınırsız.
     */
    public function geriVer(Kurum $kurum, string $gerekce, ?int $kontenjan = null): void
    {
        $eski = [
            'akreditasyon_durumu' => $kurum->akreditasyon_durumu,
            'kontenjan' => $kurum->kontenjan,
        ];

        $yeni = [
            'akreditasyon_durumu' => 'akredite',
            'kontenjan' => $kontenjan,
        ];

        $kurum->update($yeni);

        $this->denetim->yaz('kurum.akredite_edildi', $kurum,
            eski: $eski,
            yeni: $yeni,
            not: $gerekce);
    }
}
