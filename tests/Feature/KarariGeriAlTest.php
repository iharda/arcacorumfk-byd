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

    /* ─────────── Kurum durumu: kararın kuruma yazdığı sonuç ─────────── */

    /** Kurumsal başvuru + istenirse akredite bir kurumun aktif çalışan kartı. */
    private function kurumsalBasvuru(string $kurumDurumu, BasvuruDurumu $durum): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => $kurumDurumu,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => $durum,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0044',
            'basvuran_eposta' => 'iletisim@ornek.test',
            'karar_at' => now()->subDay(),
        ]);

        return [$basvuru, $kurum];
    }

    private function calisanKarti(Kurum $kurum, string $kartNo = '2026-BS-0001'): Akreditasyon
    {
        $calisan = User::create([
            'name' => 'Muhabir', 'email' => $kartNo.'@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'kullanici_id' => $calisan->id,
            'basvuran_eposta' => $calisan->email,
        ]);

        return Akreditasyon::create([
            'kullanici_id' => $calisan->id,
            'basvuru_id' => $basvuru->id,
            'kurum_id' => $kurum->id,
            'kart_no' => $kartNo,
            'yil' => 2026,
            'tur_kodu' => 'BS',
            'sira' => 1,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);
    }

    /**
     * 💀 Reddedilen kurumsal başvurunun kararı geri alındığında kurum
     * `reddedildi` KALIYORDU: Kurumlar ekranı kırmızı "Reddedildi" derken
     * Başvurular ekranı "İnceleniyor" diyordu.
     */
    public function test_red_karari_geri_alininca_kurum_beklemeye_doner(): void
    {
        [$basvuru, $kurum] = $this->kurumsalBasvuru('beklemede', BasvuruDurumu::Incelemede);

        $this->akis()->reddet($basvuru, 'Evrak yetersiz.');
        $this->assertSame('reddedildi', $kurum->fresh()->akreditasyon_durumu);

        $this->akis()->karariGeriAl($basvuru->fresh(), 'Yanlış değerlendirdim.');

        $this->assertSame(BasvuruDurumu::Incelemede, $basvuru->fresh()->durum);
        $this->assertSame('beklemede', $kurum->fresh()->akreditasyon_durumu);
    }

    /** İptal edilen kurumsal başvuruda da aynısı. */
    public function test_iptal_karari_geri_alininca_kurum_beklemeye_doner(): void
    {
        [$basvuru, $kurum] = $this->kurumsalBasvuru('beklemede', BasvuruDurumu::Gonderildi);

        $this->akis()->iptalEt($basvuru, 'Mükerrer başvuru.');
        $this->assertSame('iptal_edildi', $kurum->fresh()->akreditasyon_durumu);

        $this->akis()->karariGeriAl($basvuru->fresh(), 'Yanlışlıkla iptal ettim.');

        $this->assertSame('beklemede', $kurum->fresh()->akreditasyon_durumu);
    }

    /**
     * 🔒 `iptal` BAŞVURU KARARININ SONUCU DEĞİL: "Akreditasyonu kaldır" ile
     * verilmiş ayrı bir karardır. Geri alma onu sıfırlarsa kulübün kararı
     * sessizce silinir ve yalnızca `iptal`de açılan "geri ver" eylemi kaybolur.
     */
    public function test_kaldirilmis_akreditasyona_dokunulmaz(): void
    {
        [$basvuru, $kurum] = $this->kurumsalBasvuru('iptal', BasvuruDurumu::Onaylandi);

        $this->akis()->karariGeriAl($basvuru, 'Kararı gözden geçireceğim.');

        $this->assertSame('iptal', $kurum->fresh()->akreditasyon_durumu);
    }

    /**
     * 🪤 Kurumun BAŞKA bir onaylı kurumsal başvurusu varsa akreditasyon ona
     * dayanıyordur; eski bir kararı geri almak onu düşürmemeli.
     */
    public function test_baska_onayli_basvuru_varsa_akreditasyon_dusmez(): void
    {
        [$eski, $kurum] = $this->kurumsalBasvuru('akredite', BasvuruDurumu::Onaylandi);

        Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0045',
            'basvuran_eposta' => 'iletisim2@ornek.test',
        ]);

        $this->akis()->karariGeriAl($eski, 'Eski kararı geri alıyorum.');

        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
    }

    /* ─────────── Çalışan kartları: M9 №1'in aynısı, öbür kapıda ─────────── */

    /**
     * 💀 Kurumsal onay geri alınınca kurum `beklemede`ye düşüyor ama
     * ÇALIŞANLARIN kartları AKTİF kalıyordu: akreditasyonu düşmüş kuruluşun
     * muhabiri turnikeden geçmeye devam ediyordu. "Akreditasyonu kaldır" yolu
     * bunu sayıp soruyordu, bu yol hiç sormuyordu.
     */
    public function test_kurumsal_onay_geri_alininca_kartlar_askiya_alinabilir(): void
    {
        [$basvuru, $kurum] = $this->kurumsalBasvuru('akredite', BasvuruDurumu::Onaylandi);
        $kart = $this->calisanKarti($kurum);

        $this->assertTrue($kart->gecerliMi(), 'Başlangıçta kart geçerli olmalı.');

        $this->akis()->karariGeriAl($basvuru, 'Yanlış onayladım.', kartlariAskiyaAl: true);

        $this->assertSame('beklemede', $kurum->fresh()->akreditasyon_durumu);
        $this->assertSame(AkreditasyonDurumu::Askida, $kart->refresh()->durum);
        $this->assertFalse($kart->gecerliMi(), 'Kart turnikeden geçmemeli.');
    }

    /**
     * ⚠️ Askı İSTEĞE BAĞLI kalır (iptal gibi kalıcı değil, ama yine de bir
     * karardır). Seçilmezse kart durur -- ama kaç kartın etkilendiği DENETİME
     * yazılır ki karar sonradan hesabı sorulabilsin.
     */
    public function test_askiya_alma_secilmezse_kart_durur_ama_sayi_denetime_yazilir(): void
    {
        [$basvuru, $kurum] = $this->kurumsalBasvuru('akredite', BasvuruDurumu::Onaylandi);
        $kart = $this->calisanKarti($kurum);

        $this->akis()->karariGeriAl($basvuru, 'Yanlış onayladım.');

        $this->assertSame(AkreditasyonDurumu::Aktif, $kart->refresh()->durum);

        $kayit = DenetimKaydi::where('olay', 'basvuru.karar_geri_alindi')->latest('id')->first();

        $this->assertSame(1, $kayit->yeni['etkilenen_aktif_kart']);
        $this->assertFalse($kayit->yeni['kartlar_askiya_alindi']);
    }

    /** Bireysel başvuruda çalışan kartı kavramı yok; sayı sıfır kalmalı. */
    public function test_bireysel_geri_almada_calisan_karti_sayilmaz(): void
    {
        [$basvuru] = $this->onaylanmisBireysel();

        $this->akis()->karariGeriAl($basvuru, 'Yanlış kişi onaylandı.');

        $kayit = DenetimKaydi::where('olay', 'basvuru.karar_geri_alindi')->latest('id')->first();

        $this->assertSame(0, $kayit->yeni['etkilenen_aktif_kart']);
    }

    /* ─────────── Etiket: kararın BUGÜNKÜ karşılığı ─────────── */

    /**
     * 🪤 "Akredite edildi" GEÇMİŞ BİR KARARDIR. Kurumun akreditasyonu
     * sonradan kaldırıldığında başvuru satırı hâlâ "Akredite edildi" derken
     * Kurumlar ekranı "İptal" diyordu; ikisi de doğru ama yan yana çelişki
     * gibi duruyordu.
     */
    public function test_kurum_akreditasyonu_kalkinca_etiket_soyler(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $this->assertSame('Akredite edildi', $basvuru->durumEtiketi());

        // İptal parantez içi bir dipnot değil, bugünkü gerçek durum.
        $kurum->update(['akreditasyon_durumu' => 'iptal']);

        $this->assertSame('İptal edildi', $basvuru->fresh()->durumEtiketi());
        $this->assertSame('danger', $basvuru->fresh()->durumRengi());
        $this->assertStringContainsString(
            'iptal edildi',
            $basvuru->fresh()->durumAciklamasi(),
        );
    }

    /**
     * 🪤 "akredite değil" ile "iptal" AYNI ŞEY DEĞİL: kurum yeni bir başvuru
     * kararıyla `beklemede`ye dönmüş olabilir. Bu iptal değildir ve eski
     * dipnotlu biçimini korur -- yoksa hiç iptal edilmemiş kurum listede
     * kırmızı "İptal edildi" diye görünür.
     */
    public function test_kurum_akredite_degil_ama_iptal_de_degilse_dipnot_kalir(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'beklemede',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $this->assertSame('Akredite edildi (sonradan kaldırıldı)', $basvuru->durumEtiketi());
        $this->assertSame('success', $basvuru->durumRengi());
    }

    /** Bireyselde kartın bugünkü durumu okunur. */
    public function test_kart_iptal_olunca_etiket_soyler(): void
    {
        [$basvuru, , $kart] = $this->onaylanmisBireysel();

        $this->assertSame('Akredite edildi', $basvuru->durumEtiketi());

        // Askı GEÇİCİ bir hâl: karar duruyor, parantezli biçim korunur.
        $kart->update(['durum' => AkreditasyonDurumu::Askida]);
        $this->assertSame('Akredite edildi (askıda)', $basvuru->fresh()->durumEtiketi());
        $this->assertSame('success', $basvuru->fresh()->durumRengi());

        $kart->update(['durum' => AkreditasyonDurumu::Iptal]);
        $this->assertSame('İptal edildi', $basvuru->fresh()->durumEtiketi());
        $this->assertSame('danger', $basvuru->fresh()->durumRengi());
    }

    /** 🔒 Karara bağlanmamış başvuruda ek açıklama olmamalı. */
    public function test_diger_durumlarda_etiket_sade_kalir(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $this->assertSame('İnceleme bekliyor', $basvuru->durumEtiketi());
        $this->assertSame($basvuru->durum->aciklama(), $basvuru->durumAciklamasi());
    }

    /**
     * 🔒 Kuyruktan düşürülen başvurunun (`iptal_edildi`) etiketi eskisi gibi:
     * bu başka bir olay ve rengi de farklı (gri), akreditasyon iptali değil.
     */
    public function test_basvurunun_kendisi_iptal_edilmisse_renk_degismez(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::IptalEdildi,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $this->assertSame('İptal edildi', $basvuru->durumEtiketi());
        $this->assertSame('gray', $basvuru->durumRengi());
    }
}
