<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\DenetimKaydi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Kararın geri alınması -- Cüneyt Bey revizyonu (05.09.2026).
 *
 * 💀 Onaylandı / Reddedildi / İptal edildi BİTİŞ durumuydu: yetkili yanlış
 * karar verdiğinde ekranda hiçbir düğme kalmıyor, tek çıkış veritabanına elle
 * müdahale oluyordu.
 *
 * 🔒 KORUNAN ASIL DAVRANIŞ, durumun geri dönmesi DEĞİL, kararın SONUÇLARININ
 * toplanması: onay kart üretir, hesap açar, rol verir ve kurumsalda kurumu
 * akredite eder. Yalnızca durumu çevirmek kartı turnikede geçerli bırakırdı --
 * mümkün olan en tehlikeli yarım iş.
 */
class KarariGeriAlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
        Queue::fake();
        $this->yetkiliOlarakGir();
    }

    private function yetkiliOlarakGir(): User
    {
        $u = User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_YETKILI);
        Auth::login($u);

        return $u;
    }

    private function akis(): BasvuruAkisi
    {
        return app(BasvuruAkisi::class);
    }

    /** Onaylanmış bireysel başvuru: hesap + rol + kart. */
    private function onaylanmisBireysel(): array
    {
        $kisi = User::create([
            'name' => 'Merve Kılıç', 'email' => 'merve@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $kisi->assignRole(User::ROL_BASIN);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuru_no' => '2026-BV-0031',
            'basvuran_eposta' => 'merve@ornek.test',
            'karar_at' => now()->subDay(),
        ]);

        $kart = Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $basvuru->id,
            'kart_no' => '2026-K-0009',
            'yil' => 2026,
            'tur_kodu' => 'K',
            'sira' => 9,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        return [$basvuru, $kisi, $kart];
    }

    /** 💀 Asıl tehlike: durum döner ama kart turnikede geçerli kalır. */
    public function test_karar_geri_alininca_kart_iptal_edilir(): void
    {
        [$basvuru, , $kart] = $this->onaylanmisBireysel();

        $this->assertTrue($kart->gecerliMi(), 'Başlangıçta kart geçerli olmalı.');

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $this->assertSame(AkreditasyonDurumu::Iptal, $kart->refresh()->durum);
        $this->assertFalse($kart->gecerliMi(), 'Kart turnikeden geçmemeli.');
    }

    /** Başvuru yeniden karar verilebilir duruma döner. */
    public function test_basvuru_incelemede_durumuna_doner(): void
    {
        [$basvuru] = $this->onaylanmisBireysel();

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $basvuru->refresh();

        $this->assertSame(BasvuruDurumu::Incelemede, $basvuru->durum);
        $this->assertNull($basvuru->karar_at, 'Eski karar izi temizlenmeli.');
        $this->assertNull($basvuru->karar_veren_id);
    }

    /** Akreditasyon rolü geri alınır; hesap SİLİNMEZ, erişimi kapanır. */
    public function test_rol_geri_alinir_hesap_silinmez(): void
    {
        [$basvuru, $kisi] = $this->onaylanmisBireysel();

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $kisi->refresh();

        $this->assertFalse($kisi->hasRole(User::ROL_BASIN), 'Akreditasyon rolü kalmamalı.');
        $this->assertFalse($kisi->aktif, 'Başka dayanağı yoksa hesap pasife çekilir.');
        // Hesap kaydı DURMALI: başvurular, denetim izi ve geçiş kayıtları ona bağlı.
        $this->assertDatabaseHas('users', ['id' => $kisi->id]);
    }

    /**
     * 🔒 Kişi aynı zamanda kurum yetkilisiyse hesabı KAPATILMAZ: ilgisiz bir
     * erişimi koparmak olurdu (gazetenin sahibi hem yetkili hem muhabir).
     */
    public function test_baska_dayanagi_olan_hesap_kapatilmaz(): void
    {
        [$basvuru, $kisi] = $this->onaylanmisBireysel();
        $kisi->assignRole(User::ROL_KURUM);

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $kisi->refresh();

        $this->assertFalse($kisi->hasRole(User::ROL_BASIN));
        $this->assertTrue($kisi->hasRole(User::ROL_KURUM), 'Kurum rolüne dokunulmamalı.');
        $this->assertTrue($kisi->aktif, 'Kurum paneli erişimi kapanmamalı.');
    }

    /** Kurumsal onayda kurum akredite olmuştu; o da geri alınır. */
    public function test_kurumsal_kararda_kurum_beklemeye_doner(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-KV-0031',
            'basvuran_eposta' => 'iletisim@ornek.test',
            'karar_at' => now()->subDay(),
        ]);

        $this->akis()->karariGeriAl($basvuru, 'Evraklar hatalıymış.');

        $this->assertSame('beklemede', $kurum->refresh()->akreditasyon_durumu);
    }

    /** Reddedilen başvurunun kararı da geri alınabilir. */
    public function test_red_karari_da_geri_alinabilir(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Reddedildi,
            'basvuru_no' => '2026-BV-0032',
            'basvuran_eposta' => 'aday@ornek.test',
            'karar_at' => now()->subDay(),
            'karar_gerekcesi' => 'Evrak yetersiz',
        ]);

        $this->akis()->karariGeriAl($basvuru, 'Belge sonradan geldi.');

        $this->assertSame(BasvuruDurumu::Incelemede, $basvuru->refresh()->durum);
        $this->assertNull($basvuru->karar_gerekcesi);
    }

    /** 🔒 Kuyruktaki başvurunun "geri alınacak" kararı yok. */
    public function test_karara_baglanmamis_basvuruda_calismaz(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('karara bağlanmış');

        $this->akis()->karariGeriAl($basvuru, 'Olmaz.');
    }

    /** Geri alma denetime yazılır: "bu karar neden değişti" sorulur. */
    public function test_geri_alma_denetime_yazilir(): void
    {
        [$basvuru] = $this->onaylanmisBireysel();

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $kayit = DenetimKaydi::where('olay', 'basvuru.karar_geri_alindi')
            ->orderByDesc('id')->first();

        $this->assertNotNull($kayit);
        $this->assertStringContainsString('Yanlış kişi', (string) $kayit->not);
    }

    /** Geri alınan başvuruda normal karar akışı yeniden işler. */
    public function test_geri_alindiktan_sonra_yeniden_reddedilebilir(): void
    {
        [$basvuru] = $this->onaylanmisBireysel();

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');
        $this->akis()->reddet($basvuru->refresh(), 'Evraklar yetersiz.');

        $this->assertSame(BasvuruDurumu::Reddedildi, $basvuru->refresh()->durum);
    }
}
