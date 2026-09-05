<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Durum rozeti EKRANDA -- Cüneyt Bey revizyonu (05.09.2026).
 *
 * 🪤 `Basvuru::durumEtiketi()` birim testlerle korunuyordu ama etiketi ekrana
 * TAŞIYAN yol (Filament sütun kapanışları + Blade rozeti) hiç çalıştırılmıyordu.
 * Renk `durum->renk()`ten, etiket `durumEtiketi()`den gelirken satır "İptal
 * edildi" yazan YEŞİL bir rozet çiziyordu ve hiçbir test bunu görmüyordu.
 * Buradaki iki test sayfayı gerçekten açar.
 */
class BasvuruDurumRozetiTest extends TestCase
{
    use RefreshDatabase;

    /** Yalnızca `Basvuru::durumAciklamasi()`nin iptal dalından çıkan cümle. */
    private const ACIKLAMA = 'Başvuru onaylanmıştı ancak akreditasyon sonradan iptal edildi.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    /** Akreditasyonu iptal edilmiş, onaylanmış bir kurum başvurusu. */
    private function iptalliBasvuru(): Basvuru
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'iptal',
        ]);

        return Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Kurum Yetkilisi',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);
    }

    /**
     * Yönetim paneli 2FA zorunlu: gizli anahtar yoksa MFA ara katmanı
     * kurulum ekranına yönlendirir ve sayfa hiç açılmaz.
     */
    private function yetkili(): User
    {
        $k = User::create([
            'name' => 'Süper Kullanıcı',
            'email' => 'super@ornek.test',
            'password' => bcrypt('x'),
            'aktif' => true,
        ]);
        $k->assignRole(User::ROL_SUPER);
        $k->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $k->fresh();
    }

    /**
     * 🪤 Salt `assertSee('İptal edildi')` BU SAYFADA HİÇBİR ŞEY ÖLÇMEZ: durum
     * süzgecinin açılır listesi `BasvuruDurumu::IptalEdildi` etiketini zaten
     * her istekte basıyor, satır ne yazarsa yazsın iddia geçer. Ölçtüğümüz
     * şey satırın KENDİSİ: yalnızca yeni koddan çıkabilen açıklama cümlesi
     * ve rozetin kırmızı olması.
     */
    public function test_listede_iptal_edildi_gorunur(): void
    {
        $this->iptalliBasvuru();

        $this->actingAs($this->yetkili())
            ->get('/yonetim/basvurular')
            ->assertSuccessful()
            ->assertSee('İptal edildi')
            ->assertSee(self::ACIKLAMA)
            ->assertSee('fi-color-danger', escape: false)
            ->assertDontSee('sonradan kaldırıldı');
    }

    public function test_detayda_iptal_edildi_gorunur(): void
    {
        $basvuru = $this->iptalliBasvuru();

        $this->actingAs($this->yetkili())
            ->get("/yonetim/basvurular/{$basvuru->ulid}/inceleme")
            ->assertSuccessful()
            ->assertSee('İptal edildi')
            ->assertSee(self::ACIKLAMA)
            ->assertSee('fi-color-danger', escape: false)
            ->assertDontSee('sonradan kaldırıldı');
    }

    /**
     * 🔒 Ters yön: akreditasyonu duran başvuru kırmızıya boyanmamalı.
     * Yukarıdaki iki test rengi yalnızca "var mı" diye sorar; sayfada başka
     * bir kırmızı öğe belirirse fark etmezlerdi. Bu test farkı yakalar.
     */
    public function test_akreditasyonu_duran_basvuru_kirmiziya_boyanmaz(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Kurum Yetkilisi',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $this->actingAs($this->yetkili())
            ->get('/yonetim/basvurular')
            ->assertSuccessful()
            ->assertSee('Akredite edildi')
            ->assertDontSee(self::ACIKLAMA);
    }
}
