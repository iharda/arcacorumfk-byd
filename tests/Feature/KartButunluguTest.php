<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\DenetimKaydi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\KurumAkreditasyonu;
use App\Support\Sezon;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Kart bütünlüğü -- Tutarsızlık incelemesi M9 №1, №2, №6 ve M1-C.
 *
 * Dördü de aynı ailedendir: kural KODDA VARDI ama kimse sormuyordu, ya da
 * sütun vardı ama kimse doldurmuyordu. Hepsinin karşılığı stadyum kapısında.
 */
class KartButunluguTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
        Queue::fake();
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

    private function kurum(?int $kontenjan = null, string $durum = 'akredite'): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => $durum,
            'kontenjan' => $kontenjan,
        ]);
    }

    private function aktifKart(Kurum $kurum, string $kartNo = '2026-K-0001'): Akreditasyon
    {
        $kisi = User::create([
            'name' => 'Muhabir '.$kartNo, 'email' => $kartNo.'@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);

        // `akreditasyonlar.basvuru_id` NOT NULL: her kart bir başvurudan doğar.
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => $kartNo.'@ornek.test',
        ]);

        return Akreditasyon::create([
            'kullanici_id' => $kisi->id,
            'kurum_id' => $kurum->id,
            'basvuru_id' => $basvuru->id,
            'kart_no' => $kartNo,
            'yil' => 2026,
            'tur_kodu' => 'K',
            'sira' => (int) substr($kartNo, -1),
            'durum' => AkreditasyonDurumu::Aktif,
        ]);
    }

    /* ─────────── M9 №2 · Kartların süresi hiç dolmuyordu ─────────── */

    /** 💀 Asıl hata: sezon tanımlıyken bile yeni kart süresiz doğuyordu. */
    public function test_yeni_akreditasyon_sezon_gecerliligini_alir(): void
    {
        Ayar::yaz('sezon', '2026 / 2027');
        Ayar::yaz('sezon_baslangic', '2026-07-01');
        Ayar::yaz('sezon_bitis', '2027-06-30');

        $kisi = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $akreditasyon = app(AkreditasyonAkisi::class)->basvurudanOlustur($basvuru);

        $this->assertSame('2026 / 2027', $akreditasyon->sezon);
        $this->assertSame('2027-06-30', $akreditasyon->gecerlilik_bitis->toDateString());
        $this->assertFalse($akreditasyon->gecerlilik_bitis === null);
    }

    /**
     * 🔒 Sezon TANIMSIZSA kart üretimi DURMAZ. Yarım yapılandırma yüzünden
     * onay akışını kilitlemek kulübü maç günü çaresiz bırakırdı; eksiklik
     * panoda uyarı olarak söylenir.
     */
    public function test_sezon_tanimsizsa_kart_uretimi_durmaz(): void
    {
        $kisi = User::create([
            'name' => 'Aday', 'email' => 'aday2@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => 'aday2@ornek.test',
        ]);

        $akreditasyon = app(AkreditasyonAkisi::class)->basvurudanOlustur($basvuru);

        $this->assertNotNull($akreditasyon);
        $this->assertNull($akreditasyon->gecerlilik_bitis);
        $this->assertFalse(Sezon::tanimliMi());
    }

    /** Geçmiş bir bitiş tarihi kartı ANINDA geçersizleştirir. */
    public function test_gecerlilik_yazmak_karti_sona_erdirir(): void
    {
        $this->yetkiliOlarakGir();
        $akreditasyon = $this->aktifKart($this->kurum());

        $this->assertTrue($akreditasyon->gecerliMi(), 'Tarihsiz kart geçerli sayılır.');

        app(AkreditasyonAkisi::class)->gecerliligiYaz(
            $akreditasyon, '2025 / 2026', '2025-07-01', '2026-06-30',
        );

        $this->assertFalse($akreditasyon->refresh()->gecerliMi(), 'Süresi dolan kart geçmemeli.');
        $this->assertDatabaseHas('denetim_kaydi', ['olay' => 'akreditasyon.gecerlilik_degisti']);
    }

    /* ─────────── M9 №1 · Kurum düşünce kartlar açık kalıyordu ─────────── */

    /** 💀 Asıl hata: kurum "iptal" oluyor, çalışanların kartı turnikeden geçmeye devam ediyordu. */
    public function test_kurum_akreditasyonu_kalkinca_kartlar_askiya_alinabilir(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();
        $kart = $this->aktifKart($kurum);

        app(KurumAkreditasyonu::class)->kaldir($kurum, 'Sözleşme feshi', kartlariAskiyaAl: true);

        $this->assertSame('iptal', $kurum->refresh()->akreditasyon_durumu);
        $this->assertSame(AkreditasyonDurumu::Askida, $kart->refresh()->durum);
        $this->assertFalse($kart->gecerliMi(), 'Askıdaki kart turnikeden geçmemeli.');
    }

    /** Seçilmezse kartlara dokunulmaz -- ama kaç kart olduğu denetime yazılır. */
    public function test_kartlara_dokunulmasa_da_etkilenen_sayi_denetime_yazilir(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum();
        $this->aktifKart($kurum, '2026-K-0001');
        $this->aktifKart($kurum, '2026-K-0002');

        app(KurumAkreditasyonu::class)->kaldir($kurum, 'Sözleşme feshi');

        $kayit = DenetimKaydi::where('olay', 'kurum.akreditasyon_kaldirildi')->sole();

        $this->assertSame(2, $kayit->yeni['etkilenen_aktif_kart']);
        $this->assertFalse($kayit->yeni['kartlar_askiya_alindi']);
    }

    /* ─────────── M9 №6 · Kontenjan aşılabiliyordu ─────────── */

    /** 💀 `kontenjanDoldu()` vardı ama hiçbir yerden çağrılmıyordu. */
    public function test_kontenjani_dolu_kuruma_onay_verilemez(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum(kontenjan: 1);
        $this->aktifKart($kurum);   // kontenjan doldu

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'yeni@ornek.test',
            'basvuran_ad' => 'Yeni Muhabir',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kontenjanı dolu');

        app(BasvuruAkisi::class)->onayla($basvuru);
    }

    /** Kontenjan boşsa onay normal ilerlemeli. */
    public function test_kontenjan_bosken_onay_gecer(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum(kontenjan: 2);
        $this->aktifKart($kurum);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'yeni@ornek.test',
            'basvuran_ad' => 'Yeni Muhabir',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        app(BasvuruAkisi::class)->onayla($basvuru);

        $this->assertSame(BasvuruDurumu::Onaylandi, $basvuru->refresh()->durum);
    }

    /** Sınırsız kontenjanda (null) engel çıkmamalı. */
    public function test_sinirsiz_kontenjanda_engel_yok(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum(kontenjan: null);
        $this->aktifKart($kurum);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'yeni@ornek.test',
            'basvuran_ad' => 'Yeni Muhabir',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        app(BasvuruAkisi::class)->onayla($basvuru);

        $this->assertSame(BasvuruDurumu::Onaylandi, $basvuru->refresh()->durum);
    }

    /* ─────────── M1-C · Karar sessizce geri alınıyordu ─────────── */

    /**
     * 💀 Akreditasyonu KALDIRILMIŞ kurum yeniden başvurduğunda kayıt
     * "beklemede"ye dönüyordu: kulübün kararı kimseye sorulmadan ve hiçbir
     * yere yazılmadan geri alınmış oluyordu. Kurumlar ekranındaki
     * "Akreditasyonu geri ver" eylemi bilerek yalnız `iptal` durumuna açıkken,
     * bu yol o kuralı deliyordu.
     *
     * 🔑 Kayıt YENİDEN KULLANILMAYA devam ediyor (vergi no tekilliği kırılmasın
     * diye); değişen şey geçişin artık İZ BIRAKMASI.
     */
    public function test_akreditasyonu_kaldirilmis_kurumun_dirilisi_iz_birakir(): void
    {
        $kurum = $this->kurum(durum: 'iptal');

        // Yeni başvuru kaydı "beklemede"ye çeviriyor (kurumuHazirla'nın yaptığı).
        $kurum->update(['akreditasyon_durumu' => 'beklemede']);

        app(KurumAkreditasyonu::class)->yenidenDegerlendirmeyeAlindi($kurum, 'iptal');

        $kayit = DenetimKaydi::where('olay', 'kurum.karar_sonrasi_yeniden_basvuru')->sole();

        $this->assertSame('iptal', $kayit->eski['akreditasyon_durumu']);
        $this->assertSame('beklemede', $kayit->yeni['akreditasyon_durumu']);
        $this->assertSame($kurum->id, $kayit->kayit_id);
    }

    /** Reddedilen ve iptal edilen başvurular da karara bağlanmıştır. */
    public function test_red_ve_iptal_de_iz_birakir(): void
    {
        foreach (['reddedildi', 'iptal_edildi'] as $durum) {
            $kurum = Kurum::create([
                'resmi_unvan' => 'Kurum '.$durum,
                'akreditasyon_durumu' => 'beklemede',
            ]);

            app(KurumAkreditasyonu::class)->yenidenDegerlendirmeyeAlindi($kurum, $durum);
        }

        $this->assertSame(2, DenetimKaydi::where('olay', 'kurum.karar_sonrasi_yeniden_basvuru')->count());
    }

    /**
     * 🔒 Sıradan "beklemede" tekrarı GÜRÜLTÜ ÜRETMEMELİ. Başvurusu daha karara
     * bağlanmamış kurum formu ikinci kez gönderdiğinde bu bir "diriliş" değil;
     * denetim kaydı her düzeltmede şişerse okunmaz hâle gelir.
     */
    public function test_karara_baglanmamis_kurum_iz_birakmaz(): void
    {
        $kurum = $this->kurum(durum: 'beklemede');

        app(KurumAkreditasyonu::class)->yenidenDegerlendirmeyeAlindi($kurum, 'beklemede');

        $this->assertSame(0, DenetimKaydi::where('olay', 'kurum.karar_sonrasi_yeniden_basvuru')->count());
    }

    /**
     * 🔒 Kurumsal başvuruda kart çıkmaz; kontenjan kontrolü onu vurmamalı.
     * Vursaydı kontenjanı dolu bir kurumun KENDİ yenileme başvurusu onaylanamazdı.
     */
    public function test_kurumsal_basvuru_kontenjandan_etkilenmez(): void
    {
        $this->yetkiliOlarakGir();
        $kurum = $this->kurum(kontenjan: 1, durum: 'beklemede');
        $this->aktifKart($kurum);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_eposta' => 'iletisim@ornek.test',
            'basvuran_ad' => 'Yetkili Kişi',
            'gonderildi_at' => now()->subDay(),
            'incelemeye_alindi_at' => now(),
        ]);

        app(BasvuruAkisi::class)->onayla($basvuru);

        $this->assertSame('akredite', $kurum->refresh()->akreditasyon_durumu);
    }
}
