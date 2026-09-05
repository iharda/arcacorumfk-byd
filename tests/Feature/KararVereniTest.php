<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Kararı veren" kimdir, ekranda ne yazar?
 *
 * 💀 İki ayrı hata birleşiyordu. `reddet()` her zaman `Auth::id()` yazıyordu;
 * kurum panelinden "bu kişi çalışanımız değil" denince kulübün karar vereni
 * olarak KURUM ÇALIŞANI kaydediliyordu. Üstüne bilgi hiçbir ekranda
 * basılmadığı için kimse fark edemiyordu -- ilişki yükleniyor ama
 * kullanılmıyordu.
 */
class KararVereniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
    }

    private function akis(): BasvuruAkisi
    {
        return app(BasvuruAkisi::class);
    }

    private function kulupYetkilisi(): User
    {
        $u = User::create([
            'name' => 'Kulüp Yetkilisi', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_YETKILI);
        $u->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $u->fresh();
    }

    /** Kurum + teyit verebilecek yetkilisi + teyit bekleyen bir başvuru. */
    private function teyitBekleyen(): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $kurumYetkilisi = User::create([
            'name' => 'Kurum Personeli', 'email' => 'kurum@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);
        $kurumYetkilisi->assignRole(User::ROL_KURUM);

        $aday = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'kurum_id' => $kurum->id,
            'kullanici_id' => $aday->id,
            'basvuru_no' => '2026-BV-0051',
            'basvuran_eposta' => $aday->email,
            'kurum_teyidi_gerekli' => true,
        ]);

        return [$basvuru, $kurum, $kurumYetkilisi];
    }

    /**
     * 🔒 Kurumun "hayır"ı başvuruyu düşürür ama KULÜBÜN kararı değildir:
     * kulübün raporunda karar veren olarak kurum personeli görünmemeli.
     */
    public function test_kurum_teyidi_reddinde_karar_veren_yazilmaz(): void
    {
        [$basvuru, , $kurumYetkilisi] = $this->teyitBekleyen();

        Auth::login($kurumYetkilisi);
        $this->akis()->kurumTeyidiVer($basvuru, false, 'Bizde çalışmıyor.');

        $basvuru = $basvuru->fresh();

        $this->assertSame(BasvuruDurumu::Reddedildi, $basvuru->durum);
        $this->assertNull($basvuru->karar_veren_id, 'Kurum personeli karar veren olarak yazılmamalı.');
    }

    /**
     * 🪤 Kimse başvuruyu AÇMADI. Durum makinesi Gönderildi → Reddedildi
     * geçişine izin vermediği için İnceleniyor'dan geçmek zorunlu, ama saat
     * yazılırsa ekran "Sorumlu: (boş)" derken "İncelemeye alındı 06:27" diyor.
     */
    public function test_kurum_teyidi_reddinde_inceleme_saati_yazilmaz(): void
    {
        [$basvuru, , $kurumYetkilisi] = $this->teyitBekleyen();

        Auth::login($kurumYetkilisi);
        $this->akis()->kurumTeyidiVer($basvuru, false, 'Bizde çalışmıyor.');

        $this->assertNull($basvuru->fresh()->incelemeye_alindi_at);
    }

    /** Ekranda kişi yerine kurumun adı yazar; satır boş kalmaz. */
    public function test_kurum_teyidi_reddinde_ekranda_kurum_adi_yazar(): void
    {
        [$basvuru, $kurum, $kurumYetkilisi] = $this->teyitBekleyen();

        Auth::login($kurumYetkilisi);
        $this->akis()->kurumTeyidiVer($basvuru, false, 'Bizde çalışmıyor.');

        $this->assertSame(
            $kurum->resmi_unvan.' — teyit vermedi',
            $basvuru->fresh()->kararVereniMetni(),
        );
    }

    /** Kulüp reddinde kararı veren yetkili yazılır ve ekranda adı görünür. */
    public function test_kulup_reddinde_yetkili_yazilir(): void
    {
        $yetkili = $this->kulupYetkilisi();
        Auth::login($yetkili);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'basvuru_no' => '2026-BV-0052',
            'basvuran_eposta' => 'aday2@ornek.test',
        ]);

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $basvuru = $basvuru->fresh();

        $this->assertSame($yetkili->id, $basvuru->karar_veren_id);
        $this->assertSame('Kulüp Yetkilisi', $basvuru->kararVereniMetni());
    }

    /** Onayda da aynı: kararı veren yetkili ekranda görünür. */
    public function test_onayda_yetkili_yazilir(): void
    {
        $yetkili = $this->kulupYetkilisi();
        Auth::login($yetkili);

        $kurum = Kurum::create(['resmi_unvan' => 'A Ajans', 'akreditasyon_durumu' => 'beklemede']);
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0053',
            'basvuran_ad' => 'Kurum Yetkilisi',
            'basvuran_eposta' => 'a@ornek.test',
        ]);

        $this->akis()->onayla($basvuru);

        $this->assertSame('Kulüp Yetkilisi', $basvuru->fresh()->kararVereniMetni());
    }

    /**
     * ⚠️ İptalde kişi BİLEREK yazılmaz (BasvuruAkisi::iptalEt); satır hiç
     * çizilmesin diye null döner. Cevap denetim kaydında durur.
     */
    public function test_iptalde_karar_veren_bos_kalir(): void
    {
        Auth::login($this->kulupYetkilisi());

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuru_no' => '2026-BV-0054',
            'basvuran_eposta' => 'aday3@ornek.test',
        ]);

        $this->akis()->iptalEt($basvuru, 'Mükerrer başvuru.');

        $this->assertNull($basvuru->fresh()->kararVereniMetni());
    }

    /** 🔑 Asıl mesele bilginin EKRANA çıkması: ilişki yükleniyordu, basılmıyordu. */
    public function test_inceleme_ekraninda_karari_veren_gorunur(): void
    {
        $yetkili = $this->kulupYetkilisi();
        Auth::login($yetkili);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'basvuru_no' => '2026-BV-0055',
            'basvuran_eposta' => 'aday4@ornek.test',
        ]);

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $this->actingAs($yetkili)
            ->get("/yonetim/basvurular/{$basvuru->ulid}/inceleme")
            ->assertSuccessful()
            ->assertSee('Kararı veren')
            ->assertSee('Kulüp Yetkilisi');
    }
}
