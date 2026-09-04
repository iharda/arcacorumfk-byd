<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
use App\Models\Kurum;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param  bool  $kartlariAskiyaAl  Çalışanların aktif kartları da askıya
     *                                  alınsın mı? (M9 №1)
     */
    public function kaldir(Kurum $kurum, string $gerekce, bool $kartlariAskiyaAl = false): void
    {
        DB::transaction(function () use ($kurum, $gerekce, $kartlariAskiyaAl) {
            $eski = ['akreditasyon_durumu' => $kurum->akreditasyon_durumu];

            $kurum->update(['akreditasyon_durumu' => 'iptal']);

            /*
             * 💀 M9 №1: kurumun akreditasyonu kalkıyor ama ÇALIŞANLARININ
             * KARTLARI aktif kalıyordu. Ekran "mevcut kartlar bu adımla İPTAL
             * OLMAZ" diye doğru söylüyor, ama hiçbir yer "şu N kartı da askıya
             * alın" demiyordu; akreditasyonu kaldırılmış kuruluşun muhabiri
             * ertesi maç turnikeden geçmeye devam ediyordu.
             *
             * Kartlar HÂLÂ otomatik İPTAL edilmiyor -- iptal geri alınamaz ve
             * kişilerin kendi başvuruları olabilir. Yapılan şey: kaç kartın
             * etkilendiğini SAYMAK, denetime yazmak ve istenirse ASKIYA almak.
             * Askı geri alınabilir; yanlış düğmeye basılırsa dönülebilir.
             */
            $aktifKartlar = $kurum->akreditasyonlar()
                ->where('durum', AkreditasyonDurumu::Aktif->value)
                ->get();

            if ($kartlariAskiyaAl) {
                $neden = 'Kurumun akreditasyonu kaldırıldı — '.$gerekce;

                /** @var Akreditasyon $akreditasyon */
                foreach ($aktifKartlar as $akreditasyon) {
                    // Her kart KENDİ servis çağrısından geçer: denetim kaydı
                    // kart kart yazılsın ve kişiye bildirimi gitsin.
                    app(AkreditasyonAkisi::class)->askiyaAl($akreditasyon, $neden);
                }
            }

            $this->denetim->yaz('kurum.akreditasyon_kaldirildi', $kurum,
                eski: $eski,
                yeni: [
                    'akreditasyon_durumu' => 'iptal',
                    // "Kaç kart etkilendi" sorusunun cevabı kayıtta dursun.
                    'etkilenen_aktif_kart' => $aktifKartlar->count(),
                    'kartlar_askiya_alindi' => $kartlariAskiyaAl,
                ],
                not: $gerekce);
        });
    }

    /** Kaldırma kararının kaç aktif kartı ilgilendirdiği -- ekran bunu yazar. */
    public function aktifKartSayisi(Kurum $kurum): int
    {
        return $kurum->akreditasyonlar()
            ->where('durum', AkreditasyonDurumu::Aktif->value)
            ->count();
    }

    /** Bir daha "beklemede"ye dönerse iz bırakması gereken durumlar. */
    private const KARARA_BAGLANMIS = ['iptal', 'reddedildi', 'iptal_edildi'];

    /**
     * Karara bağlanmış kurum yeni bir başvuruyla yeniden değerlendirmeye
     * alındı -- Tutarsızlık incelemesi M1-C.
     *
     * 💀 `Kurum::yetkilininOncekiKurumu()` akredite OLMAYAN son kaydı arar,
     * yani akreditasyonu KALDIRILMIŞ (`iptal`) kurumu da bulur. Başvuru
     * verisinde `akreditasyon_durumu => 'beklemede'` olduğu için kurum yeni bir
     * başvuruyla kendiliğinden "Beklemede"ye dönüyordu: kulübün
     * "akreditasyonu kaldırdım" kararı kimseye sorulmadan ve hiçbir yere
     * yazılmadan geri alınmış oluyordu. Kurumlar ekranındaki "Akreditasyonu
     * geri ver" eylemi bilerek yalnız `iptal` durumuna açıkken, bu yol o
     * kuralı deliyordu.
     *
     * 🔑 Kayıt YENİDEN KULLANILMAYA DEVAM EDİYOR. Kullanmamak vergi no
     * tekilliğini kırar ve başvuran kendi numarasına takılıp çıkmaz sokağa
     * girer (M1-D'nin aynısı). Değişen tek şey: geçiş artık İZ BIRAKIYOR.
     */
    public function yenidenDegerlendirmeyeAlindi(Kurum $kurum, string $eskiDurum): void
    {
        if (! in_array($eskiDurum, self::KARARA_BAGLANMIS, true)) {
            return;
        }

        $this->denetim->yaz('kurum.karar_sonrasi_yeniden_basvuru', $kurum,
            eski: ['akreditasyon_durumu' => $eskiDurum],
            yeni: ['akreditasyon_durumu' => $kurum->akreditasyon_durumu],
            not: 'Karara bağlanmış kurum yeni başvuruyla yeniden değerlendirmeye alındı.',
            aktorTip: 'sistem');
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
