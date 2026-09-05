<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Kurum detayında "eksik evrak bekleniyor" bandı.
 *
 * 💀 Belge istendiği bilgisi yalnızca başvurunun inceleme ekranındaydı.
 * Kurum detayına bakan yetkili "bu kuruluşta bekleyen iş var mı" sorusunu
 * yanıtlayamıyordu; bant sekmeye girmeden görünsün diye künyenin altında.
 */
class KurumEksikEvrakBandiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->actingAs($this->yetkili());
    }

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

    private function kurum(): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);
    }

    private function belgeBeklenen(Kurum $kurum): Basvuru
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::EksikEvrak,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0060',
            'basvuran_eposta' => 'iletisim@ornek.test',
            'duzeltme_notlari' => ['veri:vergi_no' => 'Vergi numarası okunmuyor.'],
        ]);

        BasvuruDuzeltmesi::create([
            'basvuru_id' => $basvuru->id,
            'sira' => 1,
            'talep_notlari' => ['veri:vergi_no' => 'Vergi numarası okunmuyor.'],
            'talep_at' => now()->subDays(9),
        ]);

        return $basvuru;
    }

    private function detay(Kurum $kurum): TestResponse
    {
        return $this->get("/yonetim/kurumlar/{$kurum->ulid}/detay")->assertSuccessful();
    }

    public function test_bant_kunyenin_altinda_gorunur(): void
    {
        $kurum = $this->kurum();
        $this->belgeBeklenen($kurum);

        $this->detay($kurum)
            ->assertSee('Eksik evrak bekleniyor')
            ->assertSee('2026-BV-0060')
            ->assertSee('9 gündür bekliyor');
    }

    /** Sekme de neyin beklendiğini kalem kalem söylemeli. */
    public function test_evraklar_sekmesinde_beklenen_kalem_yazar(): void
    {
        $kurum = $this->kurum();
        $this->belgeBeklenen($kurum);

        $this->detay($kurum)
            ->assertSee('Yüklenmeyi bekleyen evrak var')
            ->assertSee('Vergi');
    }

    /** Belge beklenmiyorsa bant hiç çizilmez. */
    public function test_beklenen_yoksa_bant_yok(): void
    {
        $kurum = $this->kurum();

        Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0061',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $this->detay($kurum)
            ->assertDontSee('Eksik evrak bekleniyor')
            ->assertDontSee('Yüklenmeyi bekleyen evrak var');
    }

    /**
     * 🪤 ÇALIŞANIN eksik evrakı kurumun künyesinde uyarı doğurmamalı --
     * o kişinin kendi işi, kuruluşta bekleyen bir iş değil.
     */
    public function test_calisanin_eksik_evraki_bant_dogurmaz(): void
    {
        $kurum = $this->kurum();

        Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::EksikEvrak,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0062',
            'basvuran_eposta' => 'muhabir@ornek.test',
            'duzeltme_notlari' => ['veri:adres' => 'Adres eksik.'],
        ]);

        $this->detay($kurum)->assertDontSee('Eksik evrak bekleniyor');
    }
}
