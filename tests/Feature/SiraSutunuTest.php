<?php

namespace Tests\Feature;

use App\Filament\Yonetim\Resources\Kurumlar\Pages\ListKurumlar;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tabloların sıra numarası sütunu.
 *
 * 🪤 Asıl tuzak SAYFALAMA: satır numarası ham `$rowLoop` ile yazılırsa
 * 2. sayfa yeniden 1'den başlar ve "listenin 11. kaydı" diye bir şey kalmaz.
 * Filament'in `rowIndex()`i sayfa boyunu ekliyor; bu test onu kilitler.
 */
class SiraSutunuTest extends TestCase
{
    use RefreshDatabase;

    private function yetkili(): User
    {
        $u = User::create([
            'name' => 'Süper', 'email' => 'super@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_SUPER);
        $u->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $u->fresh();
    }

    /** Sıralama sabit olsun diye ünvanlar 01..12 diye numaralı. */
    private function kurumlar(int $adet): void
    {
        foreach (range(1, $adet) as $i) {
            Kurum::create([
                'resmi_unvan' => sprintf('Ajans %02d', $i),
                'akreditasyon_durumu' => 'beklemede',
                'eposta' => sprintf('ajans%02d@ornek.test', $i),
            ]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->actingAs($this->yetkili());
    }

    /** Tablonun HTML'inde görünen sıra numaraları, geldikleri sırayla. */
    private function siraNumaralari(string $html): array
    {
        // Sütun hücresi `sira_no` anahtarıyla işaretleniyor.
        preg_match_all('/wire:key="[^"]*\.sira_no"[^>]*>(.*?)<\/td>/s', $html, $m);

        return array_values(array_filter(array_map(
            fn (string $h) => trim(strip_tags($h)),
            $m[1],
        ), fn (string $d) => $d !== ''));
    }

    public function test_ilk_sayfa_birden_baslar(): void
    {
        $this->kurumlar(12);

        $numaralar = $this->siraNumaralari(
            Livewire::test(ListKurumlar::class)->html(),
        );

        $this->assertNotEmpty($numaralar, 'Sıra sütunu hiç basılmamış.');
        $this->assertSame('1', $numaralar[0]);
        $this->assertSame('10', $numaralar[9]);
    }

    /** 🔒 2. sayfa 1'e DÖNMEZ: 11'den devam eder. */
    public function test_ikinci_sayfa_kaldigi_yerden_devam_eder(): void
    {
        $this->kurumlar(12);

        $numaralar = $this->siraNumaralari(
            Livewire::test(ListKurumlar::class)->set('tableRecordsPerPage', 10)
                ->set('paginators.page', 2)->html(),
        );

        $this->assertSame(['11', '12'], $numaralar);
    }

    /** Başlık da görünüyor olmalı; sütun gizlenebilir yapılmadı. */
    public function test_baslik_gorunur(): void
    {
        $this->kurumlar(2);

        $this->get('/yonetim/kurumlar')->assertSuccessful()->assertSee('Ajans 01');
    }
}
