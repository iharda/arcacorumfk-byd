<?php

namespace Tests\Feature;

use App\Models\EvrakTuru;
use Database\Seeders\EvrakTuruSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İki dosya türü listesi birbirini tutmalı -- saha notları S6.
 *
 * 🔒 Korunan davranış: `config/bys.php`'deki genel `mime_izin` listesi ile
 * evrak türlerinin `izinli_formatlar` alanları AYNI ŞEYİ söylemeli. Biri
 * diğerinden geniş olursa ortaya "ölü izin" çıkıyor: kullanıcı biçimi
 * yüklemeye çalışıyor, sessizce reddediliyor, sebebini anlamıyor. webp tam
 * olarak böyle bir yıl boyunca reddedildi.
 */
class DosyaTuruListeleriTest extends TestCase
{
    use RefreshDatabase;

    /** Uzantı -> MIME karşılığı; genel listeyle karşılaştırmak için. */
    private const MIME = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EvrakTuruSeeder::class);
    }

    public function test_her_evrak_bicimi_genel_listede_de_var(): void
    {
        $genel = config('bys.yukleme.mime_izin');
        $eksik = [];

        foreach (EvrakTuru::all() as $tur) {
            foreach ($tur->izinli_formatlar ?? [] as $bicim) {
                $mime = self::MIME[$bicim] ?? null;

                if ($mime === null || ! in_array($mime, $genel, true)) {
                    $eksik[] = $tur->kod.' → '.$bicim;
                }
            }
        }

        $this->assertSame([], $eksik,
            'Evrak türünde izinli ama genel listede olmayan biçimler: '.implode(', ', $eksik));
    }

    /** 🪤 Asıl hata bu yöndeydi: genel listede serbest, hiçbir türde kabul yok. */
    public function test_genel_listedeki_her_bicim_en_az_bir_turde_kabul_ediliyor(): void
    {
        $kabul = EvrakTuru::all()
            ->flatMap(fn (EvrakTuru $t) => array_map(
                fn (string $b) => self::MIME[$b] ?? $b,
                $t->izinli_formatlar ?? []
            ))
            ->unique()
            ->all();

        $oluIzin = array_values(array_diff(config('bys.yukleme.mime_izin'), $kabul));

        $this->assertSame([], $oluIzin,
            'Genel listede serbest ama hiçbir evrak türünde kabul edilmeyen (ölü izin): '
            .implode(', ', $oluIzin));
    }

    public function test_webp_artik_kabul_ediliyor(): void
    {
        foreach (EvrakTuru::all() as $tur) {
            $this->assertContains('webp', $tur->izinli_formatlar ?? [], $tur->kod);
        }
    }
}
