<?php

namespace App\Console\Commands;

use App\Servisler\EvrakTaslagi;
use Illuminate\Console\Command;

/**
 * Yarim kalmis evrak taslaklarini siler.
 *
 * 🔒 KVKK: taslak dosyalarin arasinda KIMLIK BELGESI var. Basvuran formu
 * yarim birakip gittiginde o dosya diskte suresiz durmamali; oturum bittigi
 * anda zaten erisilemez hale geliyor, bu is de diskten siliyor.
 */
class EvrakTaslagiTemizle extends Command
{
    protected $signature = 'evrak:taslak-temizle {--saat=24 : Bu kadar saatten eski taslaklar silinir}';

    protected $description = 'Yarım kalmış başvuru formlarının geçici evrak taslaklarını siler';

    public function handle(EvrakTaslagi $taslak): int
    {
        $silinen = $taslak->suresiGecenleriSil((int) $this->option('saat'));

        $this->info("Silinen evrak taslağı: {$silinen}");

        return self::SUCCESS;
    }
}
