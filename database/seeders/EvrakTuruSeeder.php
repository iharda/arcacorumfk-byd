<?php

namespace Database\Seeders;

use App\Enums\BasvuruTuru;
use App\Models\EvrakTuru;
use Illuminate\Database\Seeder;

/**
 * Evrak turleri -- Plan v1.0 md.3.1 / 3.2 / 3.3.
 * Bunlar VERI; yetkili panelden ekleyip cikarabilir (Faz 2 form olusturucuya zemin).
 */
class EvrakTuruSeeder extends Seeder
{
    public function run(): void
    {
        $kurum = BasvuruTuru::Kurum->value;
        $basin = BasvuruTuru::BasinMensubu->value;
        $icerik = BasvuruTuru::IcerikUreticisi->value;

        $turler = [
            [
                'kod' => 'ticaret_sicil_gazetesi', 'ad' => 'Ticaret sicil gazetesi',
                'basvuru_turleri' => [$kurum], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 10,
            ],
            [
                'kod' => 'vergi_levhasi', 'ad' => 'Vergi levhası',
                'basvuru_turleri' => [$kurum], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 20,
            ],
            [
                'kod' => 'biyometrik_fotograf', 'ad' => 'Biyometrik fotoğraf',
                'aciklama' => 'Basın kartında ve kapı doğrulama ekranında kullanılır.',
                'basvuru_turleri' => [$basin, $icerik], 'zorunlu' => true,
                'izinli_formatlar' => ['jpg', 'jpeg', 'png'], 'maks_boyut_kb' => 5120,
                'hassas' => false, 'sira' => 30,
            ],
            [
                'kod' => 'kimlik_gorseli', 'ad' => 'Kimlik / ehliyet / pasaport',
                'aciklama' => 'At-rest şifreli saklanır; karar sonrası imha planına girer.',
                'basvuru_turleri' => [$basin, $icerik], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png'], 'maks_boyut_kb' => 8192,
                'hassas' => true, 'imha_gun' => 180, 'sira' => 40,
            ],
            [
                'kod' => 'calisma_belgesi', 'ad' => 'Çalışma / iş giriş belgesi veya SGK belgesi',
                'aciklama' => 'Kurum teyidi ayarı kapalıyken yetkili bu belgeden doğrular.',
                'basvuru_turleri' => [$basin], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png'], 'maks_boyut_kb' => 8192,
                'hassas' => true, 'imha_gun' => 180, 'sira' => 50,
            ],
        ];

        foreach ($turler as $t) {
            EvrakTuru::updateOrCreate(['kod' => $t['kod']], $t);
        }
    }
}
