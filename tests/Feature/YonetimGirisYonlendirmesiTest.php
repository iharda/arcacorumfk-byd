<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Auth\YonetimGirisi;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kamu girişinden yönetim girişine yönlendirme -- İbrahim Bey, 05.09.2026.
 *
 * 💀 Davranış doğruydu ama ANLATILMIYORDU. Kulüp yetkilisi `/giris`'te
 * e-postasını ve şifresini yazıyor, şifre DOĞRULANDIKTAN sonra oturumu
 * kapatılıp `/yonetim/login`'e atılıyordu. Tek açıklama 6 saniyede kaybolan
 * bir köşe bildirimiydi; geriye kalan ekran Filament'in çıplak varsayılanıydı
 * ve neresi olduğunu söylemiyordu. Kişi "beni neden buraya attı" diye kalıyor,
 * üstüne iki alanı da yeniden yazıyordu.
 *
 * Üç düzeltme burada korunuyor: uyarı ÖNCE veriliyor, varış sayfası kendini
 * KALICI olarak tanıtıyor, e-posta taşınıyor.
 */
class YonetimGirisYonlendirmesiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function yetkili(): User
    {
        $u = User::create([
            'name' => 'Kulüp Yetkilisi', 'email' => 'yetkili@ornek.test',
            'password' => bcrypt('cok-gizli-sifre'), 'aktif' => true,
            'email_verified_at' => now(),
        ]);
        $u->assignRole(User::ROL_YETKILI);

        return $u->fresh();
    }

    /** 1) Uyarı ÖNCE: kişi hiçbir şey yazmadan doğru kapıyı görmeli. */
    public function test_kamu_girisi_yonetim_kapisini_gorunur_gosterir(): void
    {
        $this->get('/giris')
            ->assertSuccessful()
            ->assertSee('Kulüp yetkilisi misiniz?')
            ->assertSee('Yönetim girişi ayrıdır')
            ->assertSee('İki adımlı doğrulama orada zorunludur.')
            ->assertSee(route('filament.yonetim.auth.login'), escape: false);
    }

    /** 2) Varış sayfası KALICI olarak kendini tanıtır (bildirim kaybolsa da). */
    public function test_yonetim_girisi_kendini_tanitir(): void
    {
        $this->get(route('filament.yonetim.auth.login'))
            ->assertSuccessful()
            ->assertSee('Kulüp yönetimi girişi · iki adımlı doğrulama gerekir');
    }

    /** 3) E-posta taşınır, ŞİFRE TAŞINMAZ. */
    public function test_yetkili_yonlendirilir_ve_eposta_tasinir(): void
    {
        $this->yetkili();

        $yanit = $this->post('/giris', [
            'email' => 'yetkili@ornek.test',
            'password' => 'cok-gizli-sifre',
        ]);

        $yanit->assertRedirect(route('filament.yonetim.auth.login'))
            ->assertSessionHas(YonetimGirisi::EPOSTA_ANAHTARI, 'yetkili@ornek.test');

        // 🔒 Oturum açılmamalı: iki adımlı doğrulama atlanamaz.
        $this->assertGuest();

        // 🔒 Şifre hiçbir yere yazılmaz.
        $this->assertNull(session('password'));
        foreach (session()->all() as $anahtar => $deger) {
            if (is_string($deger)) {
                $this->assertStringNotContainsString('cok-gizli-sifre', $deger, "Şifre {$anahtar} içinde sızmış.");
            }
        }
    }

    /** Taşınan e-posta yönetim giriş formunda hazır gelir. */
    public function test_tasinan_eposta_forma_doldurulur(): void
    {
        session([YonetimGirisi::EPOSTA_ANAHTARI => 'yetkili@ornek.test']);

        Livewire::test(YonetimGirisi::class)
            ->assertSet('data.email', 'yetkili@ornek.test');
    }

    /** Taşınan e-posta yoksa form boş açılır. */
    public function test_eposta_yoksa_form_bos(): void
    {
        Livewire::test(YonetimGirisi::class)
            ->assertSet('data.email', null);
    }

    /**
     * 🔒 Sıradan kullanıcı bu yönlendirmeye HİÇ girmemeli: kendi paneline
     * gider, oturumu açılır.
     */
    public function test_siradan_kullanici_etkilenmez(): void
    {
        $u = User::create([
            'name' => 'Muhabir', 'email' => 'muhabir@ornek.test',
            'password' => bcrypt('cok-gizli-sifre'), 'aktif' => true,
            'email_verified_at' => now(),
        ]);
        $u->assignRole(User::ROL_BASIN);

        // 🪤 Üye paneli AKREDİTASYON şartı arıyor (User::canAccessPanel):
        // kartı olmayan kişinin girebileceği panel yok, oturumu açılmaz.
        // Sıradan kullanıcı senaryosu ancak akredite biriyle kurulur.
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $u->id,
            'basvuru_no' => '2026-BV-0301',
            'basvuran_ad' => 'Muhabir',
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);

        Akreditasyon::create([
            'kart_no' => '2026-B-0301',
            'yil' => 2026, 'tur_kodu' => 'B', 'sira' => 301,
            'kullanici_id' => $u->id,
            'basvuru_id' => $basvuru->id,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        $yanit = $this->post('/giris', [
            'email' => 'muhabir@ornek.test',
            'password' => 'cok-gizli-sifre',
        ]);

        $yanit->assertSessionMissing(YonetimGirisi::EPOSTA_ANAHTARI);
        $this->assertAuthenticatedAs($u);
    }
}
