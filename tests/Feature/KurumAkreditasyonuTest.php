<?php

namespace Tests\Feature;

use App\Models\DenetimKaydi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\KurumAkreditasyonu;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Kurum akreditasyonunun iki yönü -- saha notları T6.
 *
 * 🔒 Korunan davranış SİMETRİ: iptal edilen kurumun geri dönüş yolu olmalı.
 * Bu test yoksa geri verme yine sessizce düşebilir; hatanın ilk hâli tam
 * olarak buydu (kurum "İptal"de kilitleniyordu).
 */
class KurumAkreditasyonuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function akis(): KurumAkreditasyonu
    {
        return app(KurumAkreditasyonu::class);
    }

    private function yetkiliOlarakGir(): User
    {
        $yetkili = User::create([
            'name' => 'Yetkili',
            'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'),
            'aktif' => true,
        ]);
        $yetkili->assignRole(User::ROL_YETKILI);
        Auth::login($yetkili);

        return $yetkili;
    }

    private function kurum(string $durum = 'akredite', ?int $kontenjan = null): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => $durum,
            'kontenjan' => $kontenjan,
        ]);
    }

    /** 🔒 Asıl hata: iptalden çıkış yolu yoktu. */
    public function test_iptal_edilen_kurum_geri_verilebiliyor(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $this->akis()->kaldir($kurum, 'Evrakları sürekli eksik.');
        $this->assertSame('iptal', $kurum->fresh()->akreditasyon_durumu);
        $this->assertFalse($kurum->fresh()->akrediteMi());

        $this->akis()->geriVer($kurum, 'Eksikler tamamlandı.');

        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
        $this->assertTrue($kurum->fresh()->akrediteMi());
    }

    public function test_geri_verirken_kontenjan_belirlenebiliyor(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum('iptal', 3);

        $this->akis()->geriVer($kurum, 'Yeniden anlaşıldı.', 10);

        $this->assertSame(10, $kurum->fresh()->kontenjan);
    }

    /** null = sınırsız; eski sayı geri verirken taşınmaz. */
    public function test_kontenjan_bos_birakilinca_sinirsiz_olur(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum('iptal', 3);

        $this->akis()->geriVer($kurum, 'Sınır kalksın.');

        $this->assertNull($kurum->fresh()->kontenjan);
        $this->assertFalse($kurum->fresh()->kontenjanDoldu());
    }

    public function test_her_iki_yon_de_denetime_gerekcesiyle_yaziliyor(): void
    {
        $yetkili = $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $this->akis()->kaldir($kurum, 'Evrak eksik.');
        $this->akis()->geriVer($kurum, 'Tamamlandı.', 5);

        $kaldirma = DenetimKaydi::where('olay', 'kurum.akreditasyon_kaldirildi')->sole();
        $this->assertSame('Evrak eksik.', $kaldirma->not);
        $this->assertSame($yetkili->id, $kaldirma->aktor_id);
        $this->assertSame('iptal', $kaldirma->yeni['akreditasyon_durumu']);

        $geri = DenetimKaydi::where('olay', 'kurum.akredite_edildi')->sole();
        $this->assertSame('Tamamlandı.', $geri->not);
        $this->assertSame('iptal', $geri->eski['akreditasyon_durumu']);
        $this->assertSame('akredite', $geri->yeni['akreditasyon_durumu']);
        $this->assertSame(5, $geri->yeni['kontenjan']);
    }

    /** Kartlar ayrı yürür: ne kaldırma ne geri verme onlara dokunur. */
    public function test_geri_verme_kart_durumuna_dokunmaz(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $this->akis()->kaldir($kurum, 'Askıya alındı.');
        $this->akis()->geriVer($kurum, 'Geri verildi.');

        $this->assertSame(0, $kurum->fresh()->akreditasyonlar()->where('durum', 'aktif')->count());
    }
}
