<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Akreditasyonlar\Pages\AkreditasyonDetay;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Akreditasyon detayının eylemleri -- T11'in devamı.
 *
 * 💀 Sayfa yalnızca "Kart PDF indir" taşıyordu: yetkili kaydı okuyup karar
 * veriyor, sonra kararı uygulamak için listeye dönüp satır menüsü açıyordu.
 *
 * 🪤 Eylemler ortak tanımdan (AkreditasyonEylemleri) geliyor ve kapanışları
 * kaydı `Akreditasyon $record` TİPİYLE istiyor. Bu enjeksiyon detay
 * sayfasında da çalışmasaydı düğmeler sessizce hiç görünmezdi -- testler
 * eylemleri gerçekten ÇAĞIRIYOR, varlıklarını saymıyor.
 */
class AkreditasyonDetayEylemleriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
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

    private function kart(AkreditasyonDurumu $durum = AkreditasyonDurumu::Aktif): Akreditasyon
    {
        $kisi = User::create([
            'name' => 'Muhabir', 'email' => 'muhabir@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuru_no' => '2026-BV-0080',
            'basvuran_eposta' => $kisi->email,
        ]);

        return Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $basvuru->id,
            'kart_no' => '2026-K-0011',
            'yil' => 2026, 'tur_kodu' => 'K', 'sira' => 11,
            'durum' => $durum,
        ]);
    }

    private function sayfa(Akreditasyon $kart)
    {
        return Livewire::test(AkreditasyonDetay::class, ['record' => $kart->ulid]);
    }

    public function test_detaydan_askiya_alinir(): void
    {
        $kart = $this->kart();

        $this->sayfa($kart)->callAction('askiyaAl', ['gerekce' => 'Belge yenilenecek.']);

        $this->assertSame(AkreditasyonDurumu::Askida, $kart->fresh()->durum);
        $this->assertFalse($kart->fresh()->gecerliMi(), 'Askıdaki kart turnikeden geçmemeli.');
    }

    /** "Yeniden verme": askı kaldırılınca kart tekrar geçerli. */
    public function test_detaydan_aski_kaldirilir(): void
    {
        $kart = $this->kart(AkreditasyonDurumu::Askida);

        $this->sayfa($kart)->callAction('yenidenAktif');

        $this->assertSame(AkreditasyonDurumu::Aktif, $kart->fresh()->durum);
        $this->assertTrue($kart->fresh()->gecerliMi());
    }

    public function test_detaydan_iptal_edilir(): void
    {
        $kart = $this->kart();

        $this->sayfa($kart)->callAction('iptal', ['neden' => 'Kurumdan ayrıldı.']);

        $this->assertSame(AkreditasyonDurumu::Iptal, $kart->fresh()->durum);
        $this->assertSame('Kurumdan ayrıldı.', $kart->fresh()->iptal_nedeni);
    }

    /** 🔒 Durum koşulları listedekiyle aynı: aktif kartın "askıyı kaldır"ı yok. */
    public function test_durum_kosullari_korunur(): void
    {
        $aktif = $this->kart();

        $this->sayfa($aktif)
            ->assertActionVisible('askiyaAl')
            ->assertActionHidden('yenidenAktif')
            ->assertActionVisible('iptal');

        $iptalli = Akreditasyon::create([
            'kullanici_id' => $aktif->kullanici_id,
            'basvuru_id' => $aktif->basvuru_id,
            'kart_no' => '2026-K-0012',
            'yil' => 2026, 'tur_kodu' => 'K', 'sira' => 12,
            'durum' => AkreditasyonDurumu::Iptal,
        ]);

        $this->sayfa($iptalli)
            ->assertActionHidden('askiyaAl')
            ->assertActionHidden('iptal');
    }

    /**
     * 💀 BU TEST ESKİDEN YANLIŞ DAVRANIŞI KORUYORDU: "Ek evrak talep et"in
     * başvuru ekranına GÖTÜRMESİNİ doğruluyordu. Gidilen yerde düğme pasifti
     * ve "önce Akreditasyonu geri al" diyordu -- yani test, yetkiliyi kartı
     * yakmaya zorlayan yolu güvenceye alıyordu (Cüneyt Bey, 05.09.2026).
     *
     * Talep artık burada açılıyor ve akreditasyona dokunmuyor; ayrıntılı
     * sözleşme AkreditasyonBelgeTalebiTest'te.
     */
    public function test_belge_talebi_detayda_acilir(): void
    {
        $kart = $this->kart();

        $this->get("/yonetim/akreditasyonlar/{$kart->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('Belge iste')
            ->assertDontSee('Ek evrak talep et');

        $this->sayfa($kart)->assertActionVisible('belgeIste');
    }
}
