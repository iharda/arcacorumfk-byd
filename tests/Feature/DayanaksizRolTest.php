<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Hesap yeniden kullanılırken ESKİ ROLLER taşınmasın.
 *
 * 💀 GERÇEK OLAY (hesap #281, denetim kaydından): Ağustos'ta bir içerik
 * üreticisi başvurusu açılmış, hesap aktifleşmiş, başvuru TASLAKTA kalmış.
 * Eylül'de aynı e-postayla KURUMSAL bir başvuru onaylanınca hesap yeniden
 * etkinleştirildi ve kişi `kurum` rolünün yanında hâlâ `icerik_ureticisi`
 * rolünü taşıyordu -- arkasında ne onaylanmış başvuru ne kart vardı.
 *
 * 🔑 Ölçü rol değil DAYANAK: bireysel rol ancak canlı bir akreditasyona
 * yaslanıyorsa kalır.
 */
class DayanaksizRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
        Queue::fake();

        $yetkili = User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $yetkili->assignRole(User::ROL_YETKILI);
        Auth::login($yetkili);
    }

    private function akis(): BasvuruAkisi
    {
        return app(BasvuruAkisi::class);
    }

    /** Ağustos'taki gibi: hesap var, rol var, arkasında akreditasyon YOK. */
    private function eskiHesap(): User
    {
        $k = User::create([
            'name' => 'Yusuf Demir', 'email' => 'yusuf@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $k->assignRole(User::ROL_ICERIK);

        Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Taslak,
            'kullanici_id' => $k->id,
            'basvuran_eposta' => $k->email,
        ]);

        return $k->fresh();
    }

    private function kurumsalBasvuru(User $kisi): Basvuru
    {
        $kurum = Kurum::create(['resmi_unvan' => 'Test Kurum 01', 'akreditasyon_durumu' => 'beklemede']);

        return Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0029',
            'basvuran_ad' => 'Yusuf Demir',
            'basvuran_eposta' => $kisi->email,
        ]);
    }

    /** 🔒 Asıl hata: kurumsal onay eski bireysel rolü yanında getiriyordu. */
    public function test_kurumsal_onay_dayanaksiz_bireysel_rolu_alir(): void
    {
        $kisi = $this->eskiHesap();
        $this->assertTrue($kisi->hasRole(User::ROL_ICERIK));

        $this->akis()->onayla($this->kurumsalBasvuru($kisi));

        $kisi = $kisi->fresh();

        $this->assertTrue($kisi->hasRole(User::ROL_KURUM), 'Kurum rolü verilmeli.');
        $this->assertFalse(
            $kisi->hasRole(User::ROL_ICERIK),
            'Dayanağı olmayan eski rol hesapla birlikte taşınmamalı.',
        );
    }

    /**
     * 🪤 TERS YÖN: gazetenin sahibi hem kurum yetkilisi hem muhabir olabilir.
     * KARTI VARSA rolü DURMALI -- temizlik körlemesine rol silmemeli.
     */
    public function test_canli_akreditasyonu_olan_rol_korunur(): void
    {
        $kisi = $this->eskiHesap();

        // Bu kez rolün arkasında gerçek bir kart var.
        $bireysel = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => $kisi->email,
        ]);

        Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $bireysel->id,
            'kart_no' => '2026-I-0001',
            'yil' => 2026, 'tur_kodu' => 'I', 'sira' => 1,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        $this->akis()->onayla($this->kurumsalBasvuru($kisi));

        $kisi = $kisi->fresh();

        $this->assertTrue($kisi->hasRole(User::ROL_KURUM));
        $this->assertTrue($kisi->hasRole(User::ROL_ICERIK), 'Kartı olan rol silinmemeli.');
    }

    /** Askıdaki kart da dayanaktır: askı geçici, iptal değil. */
    public function test_askidaki_kart_da_dayanaktir(): void
    {
        $kisi = $this->eskiHesap();

        $bireysel = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => $kisi->email,
        ]);

        Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $bireysel->id,
            'kart_no' => '2026-I-0002',
            'yil' => 2026, 'tur_kodu' => 'I', 'sira' => 2,
            'durum' => AkreditasyonDurumu::Askida,
        ]);

        $this->akis()->onayla($this->kurumsalBasvuru($kisi));

        $this->assertTrue($kisi->fresh()->hasRole(User::ROL_ICERIK));
    }

    /** İptal edilmiş kart dayanak DEĞİL. */
    public function test_iptal_kart_dayanak_sayilmaz(): void
    {
        $kisi = $this->eskiHesap();

        $bireysel = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Reddedildi,
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => $kisi->email,
        ]);

        Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $bireysel->id,
            'kart_no' => '2026-I-0003',
            'yil' => 2026, 'tur_kodu' => 'I', 'sira' => 3,
            'durum' => AkreditasyonDurumu::Iptal,
        ]);

        $this->akis()->onayla($this->kurumsalBasvuru($kisi));

        $this->assertFalse($kisi->fresh()->hasRole(User::ROL_ICERIK));
    }
}
