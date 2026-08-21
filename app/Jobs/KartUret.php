<?php

namespace App\Jobs;

use App\Models\Akreditasyon;
use App\Notifications\BasinKartiHazir;
use App\Servisler\KartUretici;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Kart üretimi -- Plan v1.0 md.6: onay anında sunucu tarafında render.
 *
 * Kuyrukta çalışır: başsız Chrome birkaç saniye sürüyor, yetkili onay
 * düğmesine basınca ekran beklemesin.
 */
class KartUret implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(
        public Akreditasyon $akreditasyon,
        public bool $bildirimGonder = true,
    ) {}

    public function handle(KartUretici $uretici): void
    {
        $kart = $uretici->uret($this->akreditasyon);

        if ($this->bildirimGonder) {
            $this->akreditasyon->kullanici?->notify(new BasinKartiHazir($this->akreditasyon, $kart));
        }
    }

    /**
     * Aynı akreditasyon için iki üretim ÜST ÜSTE binmesin.
     *
     * 💥 Burada eskiden yalnızca `uniqueId()` vardı — ama iş `ShouldBeUnique`
     * uygulamadığı için o metot HİÇBİR ŞEY YAPMIYORDU; koruma gibi görünen ölü
     * koddu. Sonuç: onayda üretilen kart ile bölge yetkisi verilince üretilen
     * kart aynı anda çalışıyor, (akreditasyon, sürüm) benzersizlik kısıtına
     * takılıyor, iş BAŞARISIZ olup yeniden deneniyordu. Her denemede boşuna
     * bir başsız Chrome çalışıyordu.
     *
     * `ShouldBeUnique` de doğru araç değil: ikinci üretim DÜŞMEMELİ, çünkü
     * bölgeler değişince kart gerçekten yenilenmeli. Doğrusu sıraya sokmak.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('kart-'.$this->akreditasyon->id))
                ->releaseAfter(5)     // çakışırsa 5 sn sonra yeniden dene
                ->expireAfter(180),   // iş çökerse kilit takılı kalmasın
        ];
    }
}
