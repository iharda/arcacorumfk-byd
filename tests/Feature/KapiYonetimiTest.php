<?php

namespace Tests\Feature;

use App\Enums\GecisSonucu;
use App\Models\DenetimKaydi;
use App\Models\KapiIstemcisi;
use App\Models\User;
use App\Servisler\KapiIstemcisiAkisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kapı yönetimi -- ertelenen ekranlar (video 02:xx, "sonraya bırakıldı").
 *
 * 🔒 Korunan asıl kural: kapının SINIRLARINI değiştiren her işlem denetim
 * kaydına düşer. Kapı açmak ve anahtar yenilemek düşüyordu; IP kısıtını
 * kaldırmak, bölge yetkisini genişletmek ve kapıyı kapatmak DÜŞMÜYORDU.
 * Uçtan uca testte korunan "IP kısıtı dışından erişim reddediliyor" sınırı,
 * panelden sessizce açılabiliyordu.
 */
class KapiYonetimiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Denetim kaydı aktörü olsun: "sistem" değil, adı geçen bir yetkili.
        $this->actingAs(User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@ornek.test', 'password' => bcrypt('x'),
        ]));
    }

    private function kapi(): KapiIstemcisi
    {
        return app(KapiIstemcisiAkisi::class)->olustur([
            'ad' => 'Kuzey turnike 1',
            'kapi_kodu' => 'KUZEY-1',
            'ip_listesi' => '10.0.0.5, 10.0.0.6',
            'bolgeler' => ['tribun'],
            'aktif' => true,
        ])['istemci'];
    }

    public function test_ip_kisitinin_kaldirilmasi_denetime_dusuyor(): void
    {
        $kapi = $this->kapi();

        app(KapiIstemcisiAkisi::class)->guncelle($kapi, [
            'ad' => 'Kuzey turnike 1',
            'kapi_kodu' => 'KUZEY-1',
            'ip_listesi' => '',          // kısıt kaldırıldı
            'bolgeler' => ['tribun'],
            'aktif' => true,
        ]);

        $this->assertNull($kapi->refresh()->ip_listesi);

        $kayit = DenetimKaydi::where('olay', 'kapi_istemcisi.guncellendi')->sole();

        $this->assertSame(['10.0.0.5', '10.0.0.6'], $kayit->eski['ip_listesi']);
        $this->assertNull($kayit->yeni['ip_listesi']);
        $this->assertSame('Yetkili', $kayit->aktor_ad);
    }

    /**
     * 🪤 Eski değerler update'ten ÖNCE okunmalı; sonra okunursa denetim kaydı
     * "eski = yeni" diye yazar (aynı tuzak Faz 2'de kurum düzenlemesinde vardı).
     */
    public function test_denetim_kaydi_yalnizca_degisen_alani_yaziyor(): void
    {
        $kapi = $this->kapi();

        app(KapiIstemcisiAkisi::class)->guncelle($kapi, [
            'ad' => 'Kuzey turnike 1 (yeni ad)',
            'kapi_kodu' => 'KUZEY-1',
            'ip_listesi' => '10.0.0.5, 10.0.0.6',
            'bolgeler' => ['tribun'],
            'aktif' => true,
        ]);

        $kayit = DenetimKaydi::where('olay', 'kapi_istemcisi.guncellendi')->sole();

        $this->assertSame(['ad'], array_keys($kayit->yeni));
        $this->assertSame('Kuzey turnike 1', $kayit->eski['ad']);
        $this->assertSame('Kuzey turnike 1 (yeni ad)', $kayit->yeni['ad']);
    }

    /** Hiçbir şey değişmediyse denetim kaydı da şişmesin. */
    public function test_degisiklik_yoksa_denetim_kaydi_yazilmiyor(): void
    {
        $kapi = $this->kapi();

        app(KapiIstemcisiAkisi::class)->guncelle($kapi, [
            'ad' => 'Kuzey turnike 1',
            'kapi_kodu' => 'KUZEY-1',
            'ip_listesi' => '10.0.0.5, 10.0.0.6',
            'bolgeler' => ['tribun'],
            'aktif' => true,
        ]);

        $this->assertSame(0, DenetimKaydi::where('olay', 'kapi_istemcisi.guncellendi')->count());
    }

    public function test_kapatma_ve_acma_ayri_olaylar_olarak_denetime_dusuyor(): void
    {
        $kapi = $this->kapi();

        app(KapiIstemcisiAkisi::class)->etkinlikDegistir($kapi, false);
        $this->assertFalse($kapi->refresh()->aktif);
        $this->assertSame(1, DenetimKaydi::where('olay', 'kapi_istemcisi.kapatildi')->count());

        app(KapiIstemcisiAkisi::class)->etkinlikDegistir($kapi, true);
        $this->assertTrue($kapi->refresh()->aktif);
        $this->assertSame(1, DenetimKaydi::where('olay', 'kapi_istemcisi.acildi')->count());
    }

    /**
     * 💀 "Yalnızca reddedilenler" süzgeci `sonuc != izinli` yazıyordu ve
     * turnikeden GEÇEN uyarı sonuçlarını da reddedilmiş gösteriyordu. Aynı
     * karışıklık listedeki rozet renginde de vardı: uyarılar YEŞİL çıkıyordu.
     */
    public function test_gecen_ve_gecemeyen_sonuclarin_tek_tanimi_var(): void
    {
        $gecenler = GecisSonucu::basarililar();

        $this->assertContains(GecisSonucu::Izinli, $gecenler);
        $this->assertContains(GecisSonucu::MukerrerOkutma, $gecenler);
        $this->assertContains(GecisSonucu::BaskaKapida, $gecenler);
        $this->assertNotContains(GecisSonucu::Askida, $gecenler);
        $this->assertNotContains(GecisSonucu::BolgeYetkisiYok, $gecenler);

        // Geçen ≠ temiz: uyarı sonuçları listede de SARI görünmeli.
        $this->assertSame('warning', GecisSonucu::MukerrerOkutma->renk());
        $this->assertSame('success', GecisSonucu::Izinli->renk());
    }
}
