<?php

namespace App\Jobs;

use App\Models\Akreditasyon;
use App\Notifications\BasinKartiHazir;
use App\Servisler\KartUretici;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

    /** Aynı akreditasyon için eşzamanlı iki üretim sürüm numarasını bozar. */
    public function uniqueId(): string
    {
        return 'kart-'.$this->akreditasyon->id;
    }
}
