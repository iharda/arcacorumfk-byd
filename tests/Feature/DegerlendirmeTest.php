<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\DegerlendirmePuani;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\DenetimKaydi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\DegerlendirmeAkisi;
use App\Servisler\HesapAcici;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Yetkili değerlendirmesi (1-5) -- Geliştirme briefi 28.08.2026, Bölüm A.
 *
 * 🔒 İki davranış burada KİLİTLİDİR ve elle bir daha denenmesin diye testtedir:
 *   1. Aynı hedefe ikinci puan YENİ SATIR AÇMAZ (kısmi benzersiz indeks).
 *   2. Puan kulüp dışına SIZMAZ -- kurum/üye rolü göremez.
 */
class DegerlendirmeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function akis(): DegerlendirmeAkisi
    {
        return app(DegerlendirmeAkisi::class);
    }

    private function kullanici(string $eposta, string $rol): User
    {
        $k = User::create([
            'name' => 'Kişi '.$eposta,
            'email' => $eposta,
            'password' => bcrypt('x'),
            'aktif' => true,
        ]);
        $k->assignRole($rol);

        return $k->fresh();
    }

    private function yetkiliOlarakGir(): User
    {
        $yetkili = $this->kullanici('yetkili@kulup.test', User::ROL_YETKILI);
        Auth::login($yetkili);

        return $yetkili;
    }

    private function kurum(string $unvan = 'Çorum Haber Ajansı'): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => $unvan,
            'akreditasyon_durumu' => 'akredite',
        ]);
    }

    public function test_yetkili_kuruma_puan_verir_ve_gunceller(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $this->akis()->kurumaYaz($kurum, 2, 'Evrakları hep eksik geliyor.');
        $this->akis()->kurumaYaz($kurum, 4, 'Düzeldi.');

        // İkinci yazma YENİ SATIR AÇMAZ.
        $this->assertSame(1, Degerlendirme::count());

        $kayit = $this->akis()->kurumIcin($kurum);
        $this->assertSame(DegerlendirmePuani::Olumlu, $kayit->puan);
        $this->assertSame('Düzeldi.', $kayit->not);
        $this->assertSame('Çorum Haber Ajansı', $kayit->hedef_ad);
        // Aktör silinse de kimin puanladığı kalsın.
        $this->assertSame('Kişi yetkili@kulup.test', $kayit->degerlendiren_ad);
    }

    public function test_kurum_rolu_degerlendirmeyi_goremez(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();
        $kayit = $this->akis()->kurumaYaz($kurum, 1, 'Görünmemeli.');

        $yetkili = Auth::user();
        $this->assertTrue($yetkili->can('viewAny', Degerlendirme::class));
        $this->assertTrue($yetkili->can('update', $kayit));

        foreach ([User::ROL_KURUM, User::ROL_BASIN, User::ROL_ICERIK] as $i => $rol) {
            $kisi = $this->kullanici("disarisi{$i}@ornek.test", $rol);

            $this->assertFalse($kisi->can('viewAny', Degerlendirme::class), $rol);
            $this->assertFalse($kisi->can('view', $kayit), $rol);
            $this->assertFalse($kisi->can('update', $kayit), $rol);
        }
    }

    /** Puan hiçbir rolde SİLİNMEZ: geçmiş denetim kaydında durur. */
    public function test_puan_silinemez(): void
    {
        $yetkili = $this->yetkiliOlarakGir();
        $kayit = $this->akis()->kurumaYaz($this->kurum(), 3, null);

        $this->assertFalse($yetkili->can('delete', $kayit));
    }

    public function test_eposta_buyuk_kucuk_harf_farki_tek_kayit_uretir(): void
    {
        $this->yetkiliOlarakGir();

        $this->akis()->kisiyeYaz('Ali@Ornek.TEST', 'Ali Veli', 2, null);
        $this->akis()->kisiyeYaz('ali@ornek.test', 'Ali Veli', 5, 'İkinci tur.');

        $this->assertSame(1, Degerlendirme::count());
        $this->assertSame('ali@ornek.test', Degerlendirme::first()->eposta);

        // Okuma da aynı kapıdan geçer: hangi yazımla sorulursa sorulsun bulur.
        foreach (['ALI@ORNEK.TEST', ' ali@ornek.test ', 'Ali@Ornek.Test'] as $yazim) {
            $this->assertSame(
                DegerlendirmePuani::CokOlumlu,
                $this->akis()->kisiIcin($yazim)?->puan,
                $yazim,
            );
        }
    }

    public function test_hesap_acilinca_kisi_puani_hesaba_baglanir(): void
    {
        $this->yetkiliOlarakGir();

        // Hesap HENÜZ YOK: puan e-postaya yazılır.
        $this->akis()->kisiyeYaz('muhabir@ornek.test', 'Yeni Muhabir', 4, 'Olumlu.');
        $this->assertNull(Degerlendirme::first()->kullanici_id);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'basvuran_ad' => 'Yeni Muhabir',
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);

        [$kullanici] = app(HesapAcici::class)->onaydanOlustur($basvuru);

        $this->assertSame($kullanici->id, Degerlendirme::first()->kullanici_id);
        // İlişki e-posta üzerinden de dönmeli (kullanicilar tablosu sütunu).
        $this->assertSame(DegerlendirmePuani::Olumlu, $kullanici->fresh()->degerlendirme?->puan);
    }

    /**
     * 💀 `users.email` projede küçük harfe indirgenmeden saklanıyor. İlişki
     * doğrudan `email` sütununa kurulsaydı bu senaryoda puan SESSİZCE
     * görünmezdi.
     */
    public function test_buyuk_harfli_hesapta_da_puan_gorunur(): void
    {
        $this->yetkiliOlarakGir();
        $kisi = $this->kullanici('Buyuk@Ornek.TEST', User::ROL_BASIN);

        $this->akis()->kisiyeYaz('Buyuk@Ornek.TEST', 'Büyük Harf', 5, null);

        $this->assertSame(DegerlendirmePuani::CokOlumlu, $kisi->fresh()->degerlendirme?->puan);
    }

    public function test_olcek_disi_puan_reddedilir(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        foreach ([0, 6, -1, 99] as $gecersiz) {
            try {
                $this->akis()->kurumaYaz($kurum, $gecersiz, null);
                $this->fail("Ölçek dışı puan kabul edildi: {$gecersiz}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('1-5', $e->getMessage());
            }
        }

        $this->assertSame(0, Degerlendirme::count());
    }

    /** Servisi atlayan yol (tinker, seeder) da yazamasın -- kısıt veritabanında. */
    public function test_veritabani_kisiti_olcek_disi_puani_reddeder(): void
    {
        $kurum = $this->kurum();

        $this->expectException(QueryException::class);

        DB::table('degerlendirmeler')->insert([
            'hedef_tip' => Degerlendirme::HEDEF_KURUM,
            'kurum_id' => $kurum->id,
            'puan' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Hedefsiz satır da veritabanından geçmesin. */
    public function test_veritabani_kisiti_hedefsiz_satiri_reddeder(): void
    {
        $this->expectException(QueryException::class);

        DB::table('degerlendirmeler')->insert([
            'hedef_tip' => Degerlendirme::HEDEF_KISI,
            'puan' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_puan_degisikligi_denetim_kaydina_duser(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $this->akis()->kurumaYaz($kurum, 2, 'İlk not.');
        $this->akis()->kurumaYaz($kurum, 5, 'Sonraki not.');

        $verildi = DenetimKaydi::where('olay', 'degerlendirme.verildi')->first();
        $guncellendi = DenetimKaydi::where('olay', 'degerlendirme.guncellendi')->first();

        $this->assertNotNull($verildi);
        $this->assertNull($verildi->eski);
        $this->assertSame(2, $verildi->yeni['puan']);

        $this->assertNotNull($guncellendi);
        $this->assertSame(2, $guncellendi->eski['puan']);
        $this->assertSame(5, $guncellendi->yeni['puan']);
        $this->assertSame('Kişi yetkili@kulup.test', $guncellendi->aktor_ad);
    }

    /**
     * Reddedilip yeniden başvuran kişinin ESKİ puanı yeni başvuru ekranında
     * görünmeli: bağ e-posta üzerinden, başvuru kaydı üzerinden değil.
     */
    public function test_yeniden_basvuruda_eski_puan_gorunur(): void
    {
        $this->yetkiliOlarakGir();
        $this->akis()->kisiyeYaz('tekrar@ornek.test', 'Tekrar Eden', 1, 'Geçen sefer sorun çıktı.');

        $yeniBasvuru = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_ad' => 'Tekrar Eden',
            'basvuran_eposta' => 'tekrar@ornek.test',
        ]);

        $kayit = $this->akis()->basvuruIcin($yeniBasvuru);

        $this->assertSame(DegerlendirmePuani::CokOlumsuz, $kayit?->puan);
        $this->assertSame('Geçen sefer sorun çıktı.', $kayit->not);
    }

    /** Kurumsal başvuruda hedef KURUM, bireysel başvuruda KİŞİ olmalı. */
    public function test_basvuru_turune_gore_dogru_hedef_secilir(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();

        $kurumsal = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Yetkili Kişi',
            'basvuran_eposta' => 'yetkili@ajans.test',
        ]);

        $this->akis()->kurumaYaz($kurum, 5, null);

        $this->assertSame(DegerlendirmePuani::CokOlumlu, $this->akis()->basvuruIcin($kurumsal)?->puan);
        // Kurumsal başvuruda YETKİLİNİN KENDİ puanı ayrı bir kayıttır.
        $this->assertNull($this->akis()->kisiIcin('yetkili@ajans.test'));
    }
}
