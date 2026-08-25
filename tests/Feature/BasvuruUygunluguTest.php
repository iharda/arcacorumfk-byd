<?php

namespace Tests\Feature;

use App\Enums\BasvuruTuru;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruUygunlugu;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kurum yetkilisinin kendi basın kartı -- Düzeltme listesi md.4.
 *
 * 💀 Eskiden iki kapı da kapalıydı ve ikisi birbirini gösteriyordu: kamuya
 * açık form "kurum panelinden yürütülür" diyordu, kurum panelindeki davet de
 * aynı mesajı veriyordu.
 */
class BasvuruUygunluguTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function akrediteKurumYetkilisi(): User
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Test Gazetesi',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $kullanici = User::create([
            'name' => 'Gazete Sahibi',
            'email' => 'sahip@ornek.test',
            'password' => bcrypt('x'),
            'kurum_id' => $kurum->id,
        ]);
        $kullanici->assignRole(User::ROL_KURUM);

        return $kullanici->fresh();
    }

    public function test_akredite_kurumun_yetkilisi_ikinci_kurum_basvurusu_yapamaz(): void
    {
        $engel = app(BasvuruUygunlugu::class)
            ->engel($this->akrediteKurumYetkilisi(), BasvuruTuru::Kurum);

        $this->assertNotNull($engel);
        $this->assertStringContainsString('akredite', $engel);
    }

    public function test_akredite_kurumun_yetkilisi_kendi_basin_kartina_basvurabilir(): void
    {
        $kullanici = $this->akrediteKurumYetkilisi();

        $this->assertNull(
            app(BasvuruUygunlugu::class)->engel($kullanici, BasvuruTuru::BasinMensubu),
            'Gazete sahibi kendi basın kartı için başvurabilmeli.',
        );

        $this->assertNull(
            app(BasvuruUygunlugu::class)->engel($kullanici, BasvuruTuru::IcerikUreticisi),
        );
    }

    /** Kurum paneli "çalışan davet et": yetkili KENDİ e-postasını yazabilmeli. */
    public function test_davet_kendi_epostasina_gonderilebilir(): void
    {
        $kullanici = $this->akrediteKurumYetkilisi();

        $this->assertNull(
            app(BasvuruUygunlugu::class)
                ->epostaIcinEngel($kullanici->email, BasvuruTuru::BasinMensubu),
        );
    }

    /** Tür verilmezse eski davranış: kulüp hesabı her hâlükârda engellidir. */
    public function test_kulup_hesabi_her_turde_engelli(): void
    {
        $yetkili = User::create([
            'name' => 'Kulüp Yetkilisi',
            'email' => 'yetkili@ornek.test',
            'password' => bcrypt('x'),
        ]);
        $yetkili->assignRole(User::ROL_YETKILI);

        foreach ([null, BasvuruTuru::Kurum, BasvuruTuru::BasinMensubu] as $tur) {
            $this->assertNotNull(app(BasvuruUygunlugu::class)->engel($yetkili->fresh(), $tur));
        }
    }
}
