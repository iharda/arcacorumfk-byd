<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\GecisSonucu;
use App\Filament\Kurum\Widgets\CalisanDurumu;
use App\Filament\Kurum\Widgets\KurumBilgisiEksik;
use App\Filament\Kurum\Widgets\SonIcerikler;
use App\Filament\Kurum\Widgets\TeyitBekleyenler;
use App\Filament\Uye\Widgets\SonDuyurular;
use App\Filament\Uye\Widgets\SonGecislerim;
use App\Filament\Uye\Widgets\YaklasanAntrenmanlar;
use App\Filament\Uye\Widgets\YapilacaklarKutusu;
use App\Filament\Yonetim\Widgets\DikkatGerektirenler;
use App\Filament\Yonetim\Widgets\GecisAkisi;
use App\Filament\Yonetim\Widgets\KuyrukSagligi;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\GecisKaydi;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Panolar -- Geliştirme briefi 28.08.2026, Bölüm B.
 *
 * 🔒 İki davranış burada KİLİTLİ:
 *   1. Üye panosu KENDİ verisinden başkasını göstermez.
 *   2. Verisi olmayan widget HİÇ render edilmez -- boş kutu, dolu kutudan kötü.
 */
class PanoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->widgetOnbelleginiTemizle();
    }

    /**
     * Widget'lar `canView()` statik olduğu için listeyi istek başına statik
     * alanda tutuyor. Testler tek süreçte koştuğundan bu alan testler arasında
     * sızar; her testten önce sıfırlanmalı.
     */
    private function widgetOnbelleginiTemizle(): void
    {
        foreach ([
            YapilacaklarKutusu::class, SonGecislerim::class,
            TeyitBekleyenler::class, KurumBilgisiEksik::class,
            DikkatGerektirenler::class,
            YaklasanAntrenmanlar::class,
            SonDuyurular::class,
            CalisanDurumu::class,
            SonIcerikler::class,
        ] as $sinif) {
            $ozellik = new \ReflectionProperty($sinif, 'onbellek');
            $ozellik->setValue(null, null);
        }
    }

    private function kullanici(string $eposta, string $rol, array $ek = []): User
    {
        $k = User::create([
            'name' => 'Kişi '.$eposta,
            'email' => $eposta,
            'password' => bcrypt('x'),
            'aktif' => true,
            'email_verified_at' => now(),
        ] + $ek);
        $k->assignRole($rol);

        return $k->fresh();
    }

    private function akreditasyon(User $kullanici, ?Kurum $kurum = null): Akreditasyon
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'kurum_id' => $kurum?->id,
            'basvuran_eposta' => $kullanici->email,
        ]);

        return Akreditasyon::create([
            'basvuru_id' => $basvuru->id,
            'kullanici_id' => $kullanici->id,
            'kurum_id' => $kurum?->id,
            'kart_no' => '2026-B-'.str_pad((string) $kullanici->id, 4, '0', STR_PAD_LEFT),
            'yil' => 2026,
            'tur_kodu' => 'B',
            'sira' => $kullanici->id,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);
    }

    /* ─────────────── Üye panosu ─────────────── */

    public function test_uye_baskasinin_gecis_kaydini_goremez(): void
    {
        $ben = $this->kullanici('ben@ornek.test', User::ROL_BASIN);
        $baskasi = $this->kullanici('baskasi@ornek.test', User::ROL_BASIN);

        $benimki = $this->akreditasyon($ben);
        $onunki = $this->akreditasyon($baskasi);

        GecisKaydi::create([
            'akreditasyon_id' => $benimki->id, 'yon' => 'giris',
            'sonuc' => GecisSonucu::Izinli, 'okundu_at' => now(),
        ]);
        GecisKaydi::create([
            'akreditasyon_id' => $onunki->id, 'yon' => 'giris',
            'sonuc' => GecisSonucu::Izinli, 'okundu_at' => now(),
        ]);

        Auth::login($ben);
        $this->widgetOnbelleginiTemizle();

        $kayitlar = SonGecislerim::kayitlar();

        $this->assertCount(1, $kayitlar);
        $this->assertSame($benimki->id, $kayitlar->first()->akreditasyon_id);
    }

    public function test_verisi_olmayan_widget_gizlenir(): void
    {
        $kisi = $this->kullanici('gecissiz@ornek.test', User::ROL_BASIN);
        $this->akreditasyon($kisi);

        Auth::login($kisi);
        $this->widgetOnbelleginiTemizle();

        // Hiç geçiş yok → kutu hiç çizilmesin.
        $this->assertFalse(SonGecislerim::canView());

        GecisKaydi::create([
            'akreditasyon_id' => $kisi->akreditasyonlar()->first()->id, 'yon' => 'giris',
            'sonuc' => GecisSonucu::Izinli, 'okundu_at' => now(),
        ]);
        $this->widgetOnbelleginiTemizle();

        $this->assertTrue(SonGecislerim::canView());
    }

    /** Profil eksiği ve doğrulanmamış e-posta yapılacaklar listesine düşer. */
    public function test_yapilacaklar_yalnizca_is_varken_gorunur(): void
    {
        $tamam = $this->kullanici('tamam@ornek.test', User::ROL_BASIN, [
            'telefon' => '+905321112233', 'il' => 'Çorum', 'ilce' => 'Merkez',
        ]);

        Auth::login($tamam);
        $this->widgetOnbelleginiTemizle();
        $this->assertFalse(YapilacaklarKutusu::canView());

        Auth::logout();
        $eksik = $this->kullanici('eksik@ornek.test', User::ROL_BASIN);
        Auth::login($eksik);
        $this->widgetOnbelleginiTemizle();

        $this->assertTrue(YapilacaklarKutusu::canView());
        $this->assertStringContainsString('Profilinizde eksik bilgi', collect(YapilacaklarKutusu::isler())->pluck('metin')->implode(' '));
    }

    /* ─────────────── Kurum panosu ─────────────── */

    public function test_kurum_panosunda_teyit_bekleyen_kendi_kurumuyla_sinirli(): void
    {
        $benimKurum = Kurum::create(['resmi_unvan' => 'Benim Ajans', 'akreditasyon_durumu' => 'akredite']);
        $digerKurum = Kurum::create(['resmi_unvan' => 'Diğer Ajans', 'akreditasyon_durumu' => 'akredite']);

        foreach ([$benimKurum, $digerKurum] as $i => $kurum) {
            Basvuru::create([
                'tur' => BasvuruTuru::BasinMensubu,
                'durum' => BasvuruDurumu::Gonderildi,
                'kurum_id' => $kurum->id,
                'kurum_teyidi_gerekli' => true,
                'gonderildi_at' => now()->subDays(2),
                'basvuran_ad' => 'Aday '.$i,
                'basvuran_eposta' => "aday{$i}@ornek.test",
            ]);
        }

        $yetkili = $this->kullanici('kurumyetkili@ornek.test', User::ROL_KURUM, ['kurum_id' => $benimKurum->id]);
        Auth::login($yetkili);
        $this->widgetOnbelleginiTemizle();

        $bekleyenler = TeyitBekleyenler::bekleyenler();

        $this->assertCount(1, $bekleyenler);
        $this->assertSame($benimKurum->id, $bekleyenler->first()->kurum_id);
    }

    public function test_kurum_bilgisi_tamsa_uyari_kutusu_cikmaz(): void
    {
        $tam = Kurum::create([
            'resmi_unvan' => 'Tam Ajans', 'akreditasyon_durumu' => 'akredite',
            'vergi_no' => '1234567890', 'adres' => 'Merkez', 'telefon' => '+903641112233',
            'eposta' => 'iletisim@ajans.test',
            'yayin_platformlari' => [['ad' => 'Site', 'url' => 'https://ornek.test']],
        ]);

        Auth::login($this->kullanici('tamkurum@ornek.test', User::ROL_KURUM, ['kurum_id' => $tam->id]));
        $this->widgetOnbelleginiTemizle();
        $this->assertFalse(KurumBilgisiEksik::canView());

        Auth::logout();
        $eksik = Kurum::create(['resmi_unvan' => 'Eksik Ajans', 'akreditasyon_durumu' => 'akredite']);
        Auth::login($this->kullanici('eksikkurum@ornek.test', User::ROL_KURUM, ['kurum_id' => $eksik->id]));
        $this->widgetOnbelleginiTemizle();

        $this->assertTrue(KurumBilgisiEksik::canView());
        $this->assertContains('Vergi numarası', KurumBilgisiEksik::eksikler());
    }

    /* ─────────────── Yönetim panosu ─────────────── */

    public function test_yonetim_panosu_yetkisiz_kullaniciya_kapali(): void
    {
        Auth::login($this->kullanici('birey@ornek.test', User::ROL_BASIN));
        $this->widgetOnbelleginiTemizle();

        $this->assertFalse(DikkatGerektirenler::canView());
        $this->assertFalse(GecisAkisi::canView());
        $this->assertFalse(KuyrukSagligi::canView());
    }

    public function test_dikkat_listesi_uzun_bekleyeni_yakalar(): void
    {
        Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'kurum_teyidi_gerekli' => false,
            'gonderildi_at' => now()->subDays(9),
            'basvuran_ad' => 'Uzun Bekleyen',
            'basvuran_eposta' => 'uzun@ornek.test',
        ]);

        Auth::login($this->kullanici('yetkili@kulup.test', User::ROL_YETKILI));
        $this->widgetOnbelleginiTemizle();

        $satirlar = DikkatGerektirenler::satirlar();

        $this->assertTrue(DikkatGerektirenler::canView());
        $this->assertSame('Kuyrukta uzun bekleyen', $satirlar->first()['sebep']);
        $this->assertStringContainsString('gündür kuyrukta', $satirlar->first()['ayrinti']);
    }

    /** Geçiş yoksa maç günü grafiği yer kaplamasın. */
    public function test_gecis_akisi_bos_gunde_gizlenir(): void
    {
        Auth::login($this->kullanici('yetkili2@kulup.test', User::ROL_YETKILI));
        $this->widgetOnbelleginiTemizle();

        $this->assertFalse(GecisAkisi::canView());
    }
}
