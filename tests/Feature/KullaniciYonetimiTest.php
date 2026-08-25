<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kullanıcı ve rol yönetimi -- Düzeltme listesi md.6.
 *
 * 🔒 En kritik davranış KİLİTLENME KORUMASI: tek yöneticinin kendini dışarıda
 * bırakması geri dönüşü olmayan bir hatadır.
 */
class KullaniciYonetimiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function kullanici(string $eposta, string $rol, bool $aktif = true): User
    {
        $k = User::create([
            'name' => 'Kişi '.$eposta,
            'email' => $eposta,
            'password' => bcrypt('x'),
            'aktif' => $aktif,
        ]);
        $k->assignRole($rol);

        return $k->fresh();
    }

    public function test_yetki_olmadan_kullanici_yonetimi_gorulemez(): void
    {
        // `kullanici.yonet` bilerek yalnızca super'de; yetkili rolünde YOK.
        $yetkili = $this->kullanici('yetkili@ornek.test', User::ROL_YETKILI);

        $this->assertFalse($yetkili->can('viewAny', User::class));
        $this->assertTrue($this->kullanici('super@ornek.test', User::ROL_SUPER)->can('viewAny', User::class));
    }

    /** 🔒 Kendi hesabını pasife alamaz, kendi rolüne dokunamaz. */
    public function test_kendine_dokunamaz(): void
    {
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        // Kilitlenme kontrolünün devreye girmemesi için ikinci bir yönetici.
        $this->kullanici('super2@ornek.test', User::ROL_SUPER);

        $this->assertFalse($super->can('pasifeAl', $super));
        $this->assertFalse($super->can('rolYonet', $super));
        $this->assertFalse($super->can('ikiAdimliSifirla', $super));
    }

    /** 🔒 Panele girebilen SON kişi pasife alınamaz. */
    public function test_son_yonetici_pasife_alinamaz(): void
    {
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        $tekYetkili = $this->kullanici('yetkili@ornek.test', User::ROL_YETKILI);

        // super + yetkili: ikisi de panele girebiliyor, yetkili kapatılabilir.
        $this->assertTrue($super->can('pasifeAl', $tekYetkili));

        $super->forceFill(['aktif' => false])->save();

        // Artık panele girebilen TEK kişi yetkili: kapatılamaz.
        $this->assertFalse($super->fresh()->can('pasifeAl', $tekYetkili->fresh()));
    }

    /** Basın mensubu pasife alınabilir: panele girebilen yönetici değil. */
    public function test_birey_hesabi_pasife_alinabilir(): void
    {
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        $basin = $this->kullanici('basin@ornek.test', User::ROL_BASIN);

        $this->assertTrue($super->can('pasifeAl', $basin));
    }

    /** 🔒 Hesap SİLİNMEZ: denetim kaydı ve akreditasyon ona bağlı. */
    public function test_hesap_silinemez(): void
    {
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        $basin = $this->kullanici('basin@ornek.test', User::ROL_BASIN);

        $this->assertFalse($super->can('delete', $basin));
    }

    /** 2FA kurulu değilse sıfırlama eylemi hiç görünmez. */
    public function test_2fa_kurulu_degilse_sifirlanamaz(): void
    {
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        $yetkili = $this->kullanici('yetkili@ornek.test', User::ROL_YETKILI);

        $this->assertFalse($super->can('ikiAdimliSifirla', $yetkili));

        $yetkili->forceFill(['iki_adimli_gizli' => 'GIZLI'])->save();

        $this->assertTrue($super->can('ikiAdimliSifirla', $yetkili->fresh()));
    }

    /**
     * 🪤 İKİ AYRI TEST: aynı test içinde art arda iki panel isteği yapmak
     * oturumda `url.intended` bırakıyor ve ikinci istek Livewire
     * yönlendiricisine düşüp 500 veriyor. Ölçtüğümüz şey yetki, oturum
     * artığı değil.
     */
    public function test_panel_sayfasi_yetkisize_kapali(): void
    {
        $this->actingAs($this->kullanici('yetkili@ornek.test', User::ROL_YETKILI))
            ->get('/yonetim/kullanicilar')
            ->assertForbidden();
    }

    public function test_panel_sayfasi_yetkiliye_acik(): void
    {
        /*
         * Yönetim panelinde 2FA ZORUNLU: gizli anahtar yoksa Filament'in MFA
         * ara katmanı kurulum ekranına yönlendirir ve sayfa hiç açılmaz.
         */
        $super = $this->kullanici('super@ornek.test', User::ROL_SUPER);
        $super->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->actingAs($super->fresh())
            ->get('/yonetim/kullanicilar')
            ->assertSuccessful()
            ->assertSee('Kullanıcılar');
    }
}
