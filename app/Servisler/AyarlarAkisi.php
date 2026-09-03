<?php

namespace App\Servisler;

use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\KapiIstemcisi;
use RuntimeException;

/**
 * Ayar yazimi -- Plan v1.0 md.8, md.10.
 *
 * 🔑 Ayarlar ekraninin govdesi sayfa sinifinda degil burada: kaydetme
 * kurallari (bolge silme korumasi, denetim kaydi, hukuki metinlerin
 * guncelleme tarihi) Livewire olmadan sinanabilsin.
 */
class AyarlarAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    /**
     * Formdan gelen ham durumu ayarlara yazar.
     *
     * @param  array<string, mixed>  $veri
     */
    public function kaydet(array $veri): void
    {
        $veri = $this->duzenle($veri);

        $this->silinecekBolgeleriDogrula((array) $veri['bolgeler']);

        foreach ($veri as $anahtar => $yeni) {
            $eski = Ayar::al($anahtar);

            if ($eski === $yeni) {
                continue;
            }

            Ayar::yaz($anahtar, $yeni);

            // Hukuki metinlerde "son guncelleme" tarihi de tutulur; kamuya
            // acik sayfada gosteriliyor.
            if (str_ends_with($anahtar, '_metni')) {
                Ayar::yaz($anahtar.'_guncelleme', now()->toDateString());
            }

            // Metinler uzun; denetim kaydina tam govdeyi degil degistigi
            // bilgisini yaziyoruz.
            $kisalt = fn ($d) => is_string($d) && mb_strlen($d) > 200
                ? mb_substr($d, 0, 200).'…'
                : $d;

            $this->denetim->yaz('ayar.degistirildi',
                eski: [$anahtar => $kisalt($eski)], yeni: [$anahtar => $kisalt($yeni)]);
        }
    }

    /**
     * 💀 "Kullanimda olan bir bolgeyi silmeyin" yalnizca bir YARDIM METNIYDI:
     * ekran uyariyi yaziyor, kod izin veriyordu. Silinen bolgenin anahtari
     * kartlarin ve kapilarin uzerinde kaliyor, tanimi kayboldugu icin
     * listelerde ham anahtar (`saha_kenari`) gorunuyor; anahtar yeniden ve
     * baska bir kodla eklenirse o kartlar sessizce yetkisiz kaliyordu.
     * Uyari artik bir GUVENCE.
     *
     * @param  array<string, string>  $yeniBolgeler
     */
    public function silinecekBolgeleriDogrula(array $yeniBolgeler): void
    {
        $silinen = array_diff(
            array_keys((array) Ayar::al('bolgeler', [])),
            array_keys($yeniBolgeler),
        );

        if ($silinen === []) {
            return;
        }

        $engeller = [];

        foreach ($silinen as $anahtar) {
            $kullanim = $this->bolgeKullanimi($anahtar);

            if ($kullanim !== []) {
                $engeller[] = '"'.$anahtar.'" ('.implode(', ', $kullanim).')';
            }
        }

        if ($engeller !== []) {
            throw new RuntimeException(
                'Kullanımda olan bölge silinemez: '.implode(' · ', $engeller)
                .'. Önce bu yetkileri kaldırın.',
            );
        }
    }

    /**
     * Bir bolgenin nerelerde kullanildigi -- insan okur cumleler.
     *
     * @return array<int, string>
     */
    private function bolgeKullanimi(string $anahtar): array
    {
        $kart = Akreditasyon::whereJsonContains('bolge_yetkileri', $anahtar)->count();
        $kapi = KapiIstemcisi::whereJsonContains('bolgeler', $anahtar)->count();
        $varsayilan = in_array($anahtar, (array) Ayar::al('varsayilan_bolgeler', []), true);

        return array_filter([
            $kart ? $kart.' akreditasyon' : null,
            $kapi ? $kapi.' kapı' : null,
            // Varsayilan listede duran bolge de kullanimdadir: silinirse yeni
            // akreditasyonlar var olmayan bir anahtarla dogar.
            $varsayilan ? 'yeni akreditasyon varsayılanı' : null,
        ]);
    }

    /**
     * Formdaki ayri alanlari ayar anahtarlarina cevirir: kart harfleri tek
     * ayarda toplanir, bolge tekrarlayicisi anahtar => ad haritasina doner.
     *
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    private function duzenle(array $veri): array
    {
        $veri['kart_tur_kodlari'] = [
            BasvuruTuru::BasinMensubu->value => strtoupper((string) $veri['kart_kodu_basin']),
            BasvuruTuru::IcerikUreticisi->value => strtoupper((string) $veri['kart_kodu_icerik']),
        ];

        $veri['bolgeler'] = collect($veri['bolgeler'] ?? [])
            ->filter(fn ($b) => filled($b['anahtar'] ?? null))
            ->mapWithKeys(fn ($b) => [$b['anahtar'] => $b['ad']])
            ->all();

        // Bos birakilan kart yili "icinde bulunulan yil" demek; 0 degil null.
        $veri['kart_yil'] = filled($veri['kart_yil'] ?? null) ? (int) $veri['kart_yil'] : null;

        unset($veri['kart_kodu_basin'], $veri['kart_kodu_icerik']);

        return $veri;
    }
}
