<?php

namespace App\Jobs;

use App\Models\Akreditasyon;
use App\Models\User;
use App\Notifications\BasinKartiHazir;
use App\Notifications\KartUretimiTamamlandi;
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
        /**
         * Kartı ÜRETTİREN kişi -- `KartUretici` içinde `auth()->id()`
         * okunuyordu ama bu iş KUYRUKTA çalışıyor ve orada oturum YOKTUR:
         * `kartlar.ureten_id` her zaman NULL kalıyordu (Düzeltme listesi
         * md.15). Tetikleyen, dispatch ANINDA parametre olarak taşınır.
         */
        public ?int $tetikleyenId = null,
    ) {
        // 🔑 Ağır iş kendi kuyruğunda: bir bültenin 500 e-postası kartı
        // bekletmesin (Düzeltme listesi md.7).
        $this->onQueue('kart');
    }

    public function handle(KartUretici $uretici): void
    {
        $kart = $uretici->uret($this->akreditasyon, $this->tetikleyenId);

        if ($this->bildirimGonder) {
            $this->akreditasyon->kullanici?->notify(new BasinKartiHazir($this->akreditasyon, $kart));
        }

        /*
         * 🔔 Üretimi TETİKLEYEN yetkiliye ayrı bildirim (T10). Düğmeye basınca
         * dönen tek şey "kuyruğa alındı" idi, sonrası sessizdi; yetkili sonucu
         * görmek için sayfayı yeniliyordu. Bildirim üyeye giden karttan ayrı:
         * biri "kartın hazır", diğeri "istediğin iş bitti".
         */
        if ($this->tetikleyenId !== null) {
            User::find($this->tetikleyenId)
                ?->notify(new KartUretimiTamamlandi($this->akreditasyon, $kart));
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
