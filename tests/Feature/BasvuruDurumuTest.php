<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruUygunlugu;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Durum makinesi -- Cüneyt Bey revizyonu (03.09.2026, "Önerilen durum adları").
 *
 * Korunan iki şey:
 *   1. Etiketler yeni, `value`'lar ESKİ. Değerler veritabanında ve denetim
 *      kaydında duruyor; biri "adı değiştirdik, değeri de değiştirelim"
 *      derse bu test durdurur.
 *   2. Düzeltmeden dönüş "İnceleme bekliyor"a DEĞİL "Yeniden inceleme
 *      bekliyor"a düşer.
 */
class BasvuruDurumuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function basvuru(BasvuruDurumu $durum = BasvuruDurumu::Gonderildi): Basvuru
    {
        return Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => $durum,
            'basvuran_ad' => 'Aybers Polat',
            'basvuran_eposta' => 'aybers+durum@ornek.test',
            'gonderildi_at' => now()->subDays(3),
        ]);
    }

    public function test_etiketler_musteri_listesiyle_ayni(): void
    {
        $beklenen = [
            'gonderildi' => 'İnceleme bekliyor',
            'incelemede' => 'İnceleniyor',
            'eksik_evrak' => 'Belge bekleniyor',
            'yeniden_inceleme' => 'Yeniden inceleme bekliyor',
            'onaylandi' => 'Onaylandı',
            'reddedildi' => 'Reddedildi',
            'iptal_edildi' => 'İptal edildi',
        ];

        foreach ($beklenen as $deger => $etiket) {
            $durum = BasvuruDurumu::from($deger);

            $this->assertSame($etiket, $durum->etiket());
            $this->assertNotSame('', $durum->aciklama());
        }
    }

    /**
     * 💀 `value` DEĞİŞMEZ: veritabanındaki başvurular, denetim kaydındaki
     * eski/yeni alanları ve CSV çıktıları bu dizelere bağlı.
     */
    public function test_veritabani_degerleri_degismedi(): void
    {
        $this->assertSame(
            ['taslak', 'gonderildi', 'incelemede', 'eksik_evrak',
                'yeniden_inceleme', 'onaylandi', 'reddedildi', 'iptal_edildi'],
            array_column(BasvuruDurumu::cases(), 'value'),
        );
    }

    public function test_yeniden_inceleme_kuyrukta_ve_acilmamis_sayilir(): void
    {
        $this->assertContains(BasvuruDurumu::YenidenInceleme, BasvuruDurumu::kuyruk());
        $this->assertContains(BasvuruDurumu::YenidenInceleme, BasvuruDurumu::acilmamis());

        // Süren başvuru sayılır: aynı e-postayla ikinci başvuru açılmamalı.
        $this->assertContains(BasvuruDurumu::YenidenInceleme, BasvuruUygunlugu::SUREN_DURUMLAR);
    }

    public function test_duzeltmeden_donus_yeniden_incelemeye_duser(): void
    {
        $basvuru = $this->basvuru(BasvuruDurumu::EksikEvrak);

        app(BasvuruAkisi::class)->gonder($basvuru);

        $this->assertSame(BasvuruDurumu::YenidenInceleme, $basvuru->refresh()->durum);
        $this->assertDatabaseHas('denetim_kaydi', ['olay' => 'basvuru.yeniden_gonderildi']);
    }

    /** İlk gönderim hâlâ "İnceleme bekliyor": iki yol karışmamalı. */
    public function test_ilk_gonderim_inceleme_bekliyora_duser(): void
    {
        $basvuru = $this->basvuru(BasvuruDurumu::Taslak);

        app(BasvuruAkisi::class)->gonder($basvuru);

        $this->assertSame(BasvuruDurumu::Gonderildi, $basvuru->refresh()->durum);
    }

    public function test_iptal_kuyruktaki_her_duraktan_yapilabilir(): void
    {
        foreach (BasvuruDurumu::kuyruk() as $durum) {
            $basvuru = $this->basvuru($durum);

            app(BasvuruAkisi::class)->iptalEt($basvuru, 'Mükerrer başvuru.');

            $this->assertSame(BasvuruDurumu::IptalEdildi, $basvuru->refresh()->durum);
            $basvuru->forceDelete();
        }
    }

    /**
     * 🔑 İPTAL BİLDİRİM DOĞURMAZ: red bir karardır ve gerekçesiyle başvurana
     * gider; iptal kaydın kapatılmasıdır.
     */
    public function test_iptal_basvurana_bildirim_gondermez(): void
    {
        app(BasvuruAkisi::class)->iptalEt($this->basvuru(), 'Başvuran telefonla vazgeçti.');

        Notification::assertNothingSent();
    }

    public function test_karara_baglanmis_basvuru_iptal_edilemez(): void
    {
        $basvuru = $this->basvuru(BasvuruDurumu::Onaylandi);

        $this->expectException(RuntimeException::class);

        app(BasvuruAkisi::class)->iptalEt($basvuru, 'Olmaz.');
    }

    /** Yetki kapısı: iptal "karar" yetkisi ister ama İncelemede şartı aramaz. */
    public function test_iptal_yetkisi_karar_verenlere_acik(): void
    {
        $this->seed(RolYetkiSeeder::class);

        $yetkili = User::create([
            'name' => 'Kulüp Yetkilisi',
            'email' => 'yetkili+iptal@ornek.test',
            'password' => bcrypt('x'),
        ]);
        $yetkili->assignRole(User::ROL_YETKILI);

        $basin = User::create([
            'name' => 'Basın Mensubu',
            'email' => 'basin+iptal@ornek.test',
            'password' => bcrypt('x'),
        ]);
        $basin->assignRole(User::ROL_BASIN);

        $basvuru = $this->basvuru();

        $this->assertTrue($yetkili->can('iptalEt', $basvuru));
        $this->assertFalse($basin->can('iptalEt', $basvuru));
    }
}
