<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\DenetimKaydi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Kurum durumunun başvuru kararıyla eşitlenmesi -- Tutarsızlık incelemesi M1.
 *
 * 💀 Kurum satırı başvuru anında `beklemede` doğuyor, onayda `akredite`
 * oluyordu; red ve iptal kuruma HİÇ DOKUNMUYORDU. Kullanıcının bildirdiği
 * tablo buydu: kurum Kurumlar listesinde sonsuza kadar "Beklemede" görünüyor,
 * Başvurular ekranında ise varsayılan kuyruk süzgeci karara bağlananı
 * gizlediği için karşılığı bulunamıyordu.
 */
class KurumDurumSenkronuTest extends TestCase
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

    private function yetkiliOlarakGir(): User
    {
        $yetkili = User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $yetkili->assignRole(User::ROL_YETKILI);
        Auth::login($yetkili);

        return $yetkili;
    }

    /** @return array{Kurum, Basvuru} */
    private function kurumBasvurusu(string $kurumDurumu = 'beklemede'): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => $kurumDurumu,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-KV-0001',
            'basvuran_eposta' => 'iletisim@ornek.test',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        return [$kurum, $basvuru];
    }

    /** 💀 Asıl hata: reddedilen kurum sonsuza kadar "Beklemede" kalıyordu. */
    public function test_red_karari_kuruma_da_yazilir(): void
    {
        $this->yetkiliOlarakGir();
        [$kurum, $basvuru] = $this->kurumBasvurusu();

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $this->assertSame('reddedildi', $kurum->refresh()->akreditasyon_durumu);
    }

    /** İptal de karara bağlanmıştır; kurum "Beklemede" kalmamalı. */
    public function test_iptal_karari_kuruma_da_yazilir(): void
    {
        $this->yetkiliOlarakGir();
        [$kurum, $basvuru] = $this->kurumBasvurusu();

        $this->akis()->iptalEt($basvuru, 'Mükerrer başvuru.');

        $this->assertSame('iptal_edildi', $kurum->refresh()->akreditasyon_durumu);
    }

    /**
     * 🔒 EN ÖNEMLİ KORUMA: akredite bir kurumun SONRAKİ bir başvurusu
     * reddedilirse akreditasyonu DÜŞMEMELİ. Akreditasyon kaldırma ayrı ve
     * bilinçli bir eylem (KurumAkreditasyonu); red kararı onun yerine geçemez.
     */
    public function test_akredite_kurum_sonraki_reddedilen_basvurudan_etkilenmez(): void
    {
        $this->yetkiliOlarakGir();
        [$kurum, $basvuru] = $this->kurumBasvurusu(kurumDurumu: 'akredite');

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $this->assertSame('akredite', $kurum->refresh()->akreditasyon_durumu);
    }

    /**
     * 🔒 `iptal` (akreditasyon kaldırıldı) ile `iptal_edildi` (başvuru düştü)
     * AYRI kalmalı: "Akreditasyonu geri ver" eylemi yalnız `iptal`e açılır,
     * hiç akredite olmamış kuruma açılmamalı.
     */
    public function test_akreditasyonu_kaldirilmis_kurum_ezilmez(): void
    {
        $this->yetkiliOlarakGir();
        [$kurum, $basvuru] = $this->kurumBasvurusu(kurumDurumu: 'iptal');

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $this->assertSame('iptal', $kurum->refresh()->akreditasyon_durumu);
    }

    /** Bireysel başvurunun reddi hiçbir kuruma dokunmamalı. */
    public function test_bireysel_red_kuruma_dokunmaz(): void
    {
        $this->yetkiliOlarakGir();

        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'beklemede',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0001',
            'basvuran_eposta' => 'muhabir@ornek.test',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $this->assertSame('beklemede', $kurum->refresh()->akreditasyon_durumu);
    }

    /** Karar denetim kaydına düşmeli: kurumun durumunu neyin değiştirdiği sorulur. */
    public function test_kurum_durum_degisikligi_denetime_yazilir(): void
    {
        $this->yetkiliOlarakGir();
        [$kurum, $basvuru] = $this->kurumBasvurusu();

        $this->akis()->reddet($basvuru, 'Evraklar yetersiz.');

        $kayit = DenetimKaydi::where('olay', 'kurum.durum_degisti')->sole();

        $this->assertSame($kurum->id, $kayit->kayit_id);
        $this->assertStringContainsString('2026-KV-0001', (string) $kayit->not);
    }

    /**
     * 💀 M1-D: başvuru yumuşak silinince bağ kopuyordu. Yetkili yeniden
     * başvurduğunda eski kurum bulunamıyor, YENİ satır açılıyor ve vergi no
     * tekilliği kendi eski kaydını hariç tutamadığı için başvuran çıkmaz
     * sokağa giriyordu.
     */
    public function test_yumusak_silinmis_basvurunun_kurumu_yeniden_bulunur(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'beklemede',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Gonderildi,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $basvuru->delete();   // yumuşak silme

        $this->assertTrue($basvuru->trashed());
        $this->assertSame(
            $kurum->id,
            Kurum::yetkilininOncekiKurumu('iletisim@ornek.test')?->id,
            'Silinmiş başvuru da kurumun kime ait olduğunu söylemeye devam etmeli.'
        );
    }
}
