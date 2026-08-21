<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\GecisSonucu;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\GecisKaydi;
use App\Models\KapiIstemcisi;
use Illuminate\Support\Facades\Cache;

/**
 * Turnike / gişe doğrulaması -- Plan v1.0 md.6 ve md.7.
 *
 * 🔑 Yetki KARTTA DEĞİL burada. QR yalnızca imzalı bir referans taşır; kimin
 * nereye girebileceği her okutmada bu servisten sorulur. İptal edilen kart
 * bir sonraki okutmada geçersizdir — kart geri toplanmaz.
 *
 * 📝 BAŞARISIZ okutmalar da loglanır (imza geçersiz, bulunamadı): saldırı ve
 * sahte kart tespiti için en değerli kayıt bunlar.
 */
class KapiDogrulama
{
    public function __construct(private QrImzalayici $qr) {}

    /**
     * @return array{sonuc: GecisSonucu, akreditasyon: ?Akreditasyon, mesaj: string}
     */
    public function dogrula(
        KapiIstemcisi $istemci,
        string $yuk,
        string $yon = 'giris',
        ?string $bolge = null,
        ?string $ip = null,
    ): array {
        $cozum = $this->qr->coz($yuk);

        if ($cozum === null) {
            // 🔒 Ham QR yükünü LOGA YAZMA: kurcalanmış da olsa içinde gerçek bir
            // akreditasyon referansı olabilir. Aynı sahte kartın tekrar tekrar
            // denendiğini görebilmek için parmak izini saklıyoruz.
            return $this->sonucla($istemci, GecisSonucu::ImzaGecersiz, null, $yon, $bolge, $ip,
                'Kart okunamadı veya imza geçersiz.', 'sha256:' . substr(hash('sha256', $yuk), 0, 24));
        }

        $akreditasyon = Akreditasyon::with(['kullanici', 'kurum'])
            ->where('ulid', $cozum['ulid'])
            ->first();

        if ($akreditasyon === null) {
            return $this->sonucla($istemci, GecisSonucu::Bulunamadi, null, $yon, $bolge, $ip,
                'Bu karta ait kayıt bulunamadı.', $cozum['ulid']);
        }

        // Kapıya bölge atanmışsa istekteki bölge yerine ONU kullan: cihazın
        // gönderdiği bölgeye güvenmeyelim.
        $etkinBolge = filled($istemci->bolgeler) ? ($bolge ?: $istemci->bolgeler[0]) : $bolge;
        if (filled($istemci->bolgeler) && $etkinBolge && ! in_array($etkinBolge, $istemci->bolgeler, true)) {
            $etkinBolge = $istemci->bolgeler[0];
        }

        $sonuc = match (true) {
            $akreditasyon->durum === AkreditasyonDurumu::Iptal  => GecisSonucu::Iptal,
            $akreditasyon->durum === AkreditasyonDurumu::Askida => GecisSonucu::Askida,
            ! $akreditasyon->gecerliMi()                        => GecisSonucu::Iptal,
            $etkinBolge && ! $akreditasyon->gecerliMi($etkinBolge) => GecisSonucu::BolgeYetkisiYok,
            $this->mukerrerMi($akreditasyon, $istemci)          => GecisSonucu::MukerrerOkutma,
            default                                             => GecisSonucu::Izinli,
        };

        return $this->sonucla($istemci, $sonuc, $akreditasyon, $yon, $etkinBolge, $ip,
            $this->mesaj($sonuc, $akreditasyon), $akreditasyon->ulid);
    }

    /**
     * Aynı kart kısa süre içinde aynı kapıda tekrar okutulduysa işaretle.
     * Geçişi ENGELLEMEZ — görevliye "bu kart az önce okutuldu" der; kart
     * paylaşımını yakalamanın en pratik yolu bu.
     */
    private function mukerrerMi(Akreditasyon $akreditasyon, KapiIstemcisi $istemci): bool
    {
        $saniye = (int) Ayar::al('mukerrer_okutma_saniye', 30);

        if ($saniye <= 0) {
            return false;
        }

        return GecisKaydi::query()
            ->where('akreditasyon_id', $akreditasyon->id)
            ->where('kapi_istemcisi_id', $istemci->id)
            ->where('sonuc', GecisSonucu::Izinli->value)
            ->where('okundu_at', '>=', now()->subSeconds($saniye))
            ->exists();
    }

    private function sonucla(
        KapiIstemcisi $istemci,
        GecisSonucu $sonuc,
        ?Akreditasyon $akreditasyon,
        string $yon,
        ?string $bolge,
        ?string $ip,
        string $mesaj,
        ?string $referans,
    ): array {
        GecisKaydi::create([
            'akreditasyon_id'   => $akreditasyon?->id,
            'kapi_istemcisi_id' => $istemci->id,
            'kapi_kodu'         => $istemci->kapi_kodu,
            'yon'               => $yon,
            'sonuc'             => $sonuc,
            'bolge'             => $bolge,
            'sebep'             => $sonuc->basarili() ? null : $mesaj,
            // Ham QR yükü DEĞİL yalnızca referansı: kişisel veri log'a düşmesin.
            'okunan_referans'   => $referans,
            'ip'                => $ip,
            'okundu_at'         => now(),
            'created_at'        => now(),
        ]);

        $istemci->forceFill(['son_kullanim_at' => now(), 'son_kullanim_ip' => $ip])->saveQuietly();

        return ['sonuc' => $sonuc, 'akreditasyon' => $akreditasyon, 'mesaj' => $mesaj];
    }

    private function mesaj(GecisSonucu $sonuc, Akreditasyon $akreditasyon): string
    {
        return match ($sonuc) {
            GecisSonucu::Izinli          => 'Geçiş izinli.',
            GecisSonucu::Askida          => 'Akreditasyon askıda.',
            GecisSonucu::Iptal           => 'Akreditasyon geçerli değil.',
            GecisSonucu::BolgeYetkisiYok => 'Bu bölge için yetkisi yok.',
            GecisSonucu::MukerrerOkutma  => 'Bu kart az önce okutuldu.',
            default                      => 'Geçiş reddedildi.',
        };
    }

    /**
     * Kapı ekranında gösterilecek fotoğraf. Görevli YÜZ KONTROLÜ yapacak,
     * bu yüzden fotoğraf zorunlu (md.6).
     * Önbelleğe alınır: maç günü aynı kişi defalarca okutulabilir.
     */
    public function fotoVeri(Akreditasyon $akreditasyon): ?string
    {
        return Cache::remember(
            "byd.kapi.foto.{$akreditasyon->ulid}",
            now()->addHours(12),
            function () use ($akreditasyon) {
                $akreditasyon->loadMissing('basvuru.evraklar.turu');

                $foto = $akreditasyon->basvuru?->evraklar
                    ->first(fn ($e) => $e->turu?->kod === 'biyometrik_fotograf');

                if (! $foto) {
                    return null;
                }

                return rescue(function () use ($foto) {
                    $ham = app(EvrakYukleyici::class)->icerik($foto);

                    return 'data:image/jpeg;base64,' . base64_encode($this->kucult($ham));
                }, null, report: false);
            },
        );
    }

    /** Kapı ekranı için küçült: her okutmada 5 MB göndermenin anlamı yok. */
    private function kucult(string $ham, int $hedefEn = 420): string
    {
        $resim = @imagecreatefromstring($ham);

        if ($resim === false) {
            return $ham;
        }

        $en = imagesx($resim);
        $boy = imagesy($resim);

        if ($en > $hedefEn) {
            $yeniBoy = (int) round($boy * $hedefEn / $en);
            $kucuk = imagescale($resim, $hedefEn, $yeniBoy);
            imagedestroy($resim);
            $resim = $kucuk;
        }

        ob_start();
        imagejpeg($resim, null, 82);
        imagedestroy($resim);

        return (string) ob_get_clean();
    }
}
