<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Ortak\BelgeTalebiEylemi;
use App\Filament\Yonetim\Resources\Kurumlar\Pages\KurumDetay;
use App\Filament\Yonetim\Widgets\DikkatGerektirenler;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use Database\Seeders\AyarSeeder;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AKREDİTE KURULUŞTAN BELGE TALEBİ -- Test User 2 vakası (05.09.2026).
 *
 * 💀 Belge talebi ilk sürümde yalnız KİŞİ tarafına yapılmıştı: düğme
 * `AkreditasyonDetay`'da yaşıyor ve o sayfa bir akreditasyon kaydı gerektiriyor.
 * Kurumsal onayda böyle bir kayıt DOĞMUYOR (kart kişiye çıkar, kuruluşa değil),
 * dolayısıyla onaylanmış bir kurum başvurusunda üç düğme de kapalıydı ve belge
 * istemenin tek yolu yine "Akreditasyonu geri al" idi -- düzeltmeye çalıştığımız
 * şeyin ta kendisi, sadece öbür kapıda.
 *
 * Korunan sözleşme: talep açılır, KURULUŞUN AKREDİTASYONU AKREDİTE KALIR,
 * başvurunun durumu değişmez, kuruluş kendi panelinden görüp yükler.
 */
class KurumBelgeTalebiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->seed(AyarSeeder::class);

        // Pano widget'ı satırlarını statik olarak belliyor; testler arasında
        // sızmasın (bkz. DikkatGerektirenler::onbellegiUnut).
        DikkatGerektirenler::onbellegiUnut();
    }

    private function yetkili(): User
    {
        $u = User::create([
            'name' => 'Süper', 'email' => 'super@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_SUPER);
        $u->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $u->fresh();
    }

    private function evrakTuru(): EvrakTuru
    {
        return EvrakTuru::create([
            'kod' => 'ticaret_sicil_gazetesi',
            'ad' => 'Ticaret Sicili Gazetesi',
            'basvuru_turleri' => [BasvuruTuru::Kurum->value],
            'zorunlu' => true,
            'izinli_formatlar' => ['pdf', 'jpg'],
            'maks_boyut_kb' => 4096,
            'hassas' => false,
            'sira' => 1,
            'aktif' => true,
        ]);
    }

    /** Akredite kuruluş: hesabı olan yetkilisi ve ONAYLANMIŞ kurumsal başvurusu. */
    private function akrediteKurum(): Kurum
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
            'eposta' => 'iletisim@ornek.test',
        ]);

        $yetkili = User::create([
            'name' => 'Kurum Yetkilisi', 'email' => 'kurumyetkili@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
            'email_verified_at' => now(), 'kurum_id' => $kurum->id,
        ]);
        $yetkili->assignRole(User::ROL_KURUM);

        Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'kullanici_id' => $yetkili->id,
            'basvuru_no' => '2026-BV-0201',
            'basvuran_ad' => 'Kurum Yetkilisi',
            'basvuran_eposta' => 'kurumyetkili@ornek.test',
            'karar_gerekcesi' => 'Belgeleri tamam, onaylandı.',
        ]);

        return $kurum->fresh();
    }

    private function talepEt(Kurum $kurum, int $gun = 7): void
    {
        app(BasvuruAkisi::class)->belgeTalepEt(
            $kurum->onayliKurumsalBasvuru(),
            ['evrak:ticaret_sicil_gazetesi' => 'Gazetenin güncel nüshasını gönderin.'],
            'Sezon başı güncellemesi.',
            [],
            $gun,
        );
    }

    /* ─────────────────────────── SERVİS ─────────────────────────── */

    public function test_talep_kurumun_akreditasyonunu_degistirmez(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();
        $this->talepEt($kurum);

        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
        $this->assertSame(BasvuruDurumu::Onaylandi, $kurum->onayliKurumsalBasvuru()->durum);
        $this->assertNotNull($kurum->onayliKurumsalBasvuru()->acikBelgeTalebi());
    }

    /**
     * 🪤 `onayliKurumsalBasvuru()` ÇALIŞANIN bireysel başvurusunu seçmemeli:
     * `basvurular()` ilişkisi `kurum_id` üzerinden çalışıyor ve muhabirin
     * başvurusu da o kuruma bağlı. Yanlış dosyaya belge istenirdi.
     */
    public function test_calisanin_bireysel_basvurusu_hedef_alinmaz(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        $bireysel = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0202',
            'basvuran_ad' => 'Muhabir',
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);

        $hedef = $kurum->fresh()->onayliKurumsalBasvuru();

        $this->assertSame('2026-BV-0201', $hedef->basvuru_no);
        $this->assertNotSame($bireysel->id, $hedef->id);
    }

    /* ─────────────────────────── EKRANLAR ─────────────────────────── */

    public function test_kurum_detayinda_belge_iste_eylemi_var(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        $this->get("/yonetim/kurumlar/{$kurum->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('Belge iste');

        $this->assertNull(BelgeTalebiEylemi::kurumEngeli($kurum));
    }

    /** 🔒 Akreditasyonu düşmüş kuruluşta düğme pasif. */
    public function test_akredite_olmayan_kurumda_eylem_pasif(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();
        $kurum->forceFill(['akreditasyon_durumu' => 'iptal'])->save();

        $this->assertSame(
            'Belge talebi yalnızca AKREDİTE kuruluşta açılır.',
            BelgeTalebiEylemi::kurumEngeli($kurum->fresh()),
        );
    }

    /** Onaylanmış kurumsal başvurusu olmayan akredite kuruluşta da pasif. */
    public function test_onayli_basvuru_yoksa_eylem_pasif(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();

        $kurum = Kurum::create([
            'resmi_unvan' => 'Başvurusuz Ajans',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $this->assertStringContainsString(
            'onaylanmış kurumsal başvurusu yok',
            BelgeTalebiEylemi::kurumEngeli($kurum) ?? '',
        );
    }

    public function test_detayda_bant_ve_sure_gorunur(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();
        $this->talepEt($kurum);

        $this->get("/yonetim/kurumlar/{$kurum->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('Belge bekleniyor')
            ->assertSee('Akreditasyonu AKREDİTE kalmaya', false)
            // Eksik evrak bandı ile karışmasın: o metin çıkmamalı.
            ->assertDontSee('Eksik evrak bekleniyor');
    }

    /** Modal gerçekten çalışıyor: kurumdan talep açılıyor. */
    public function test_modaldan_talep_acilir(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();

        Livewire::test(KurumDetay::class, ['record' => $kurum->ulid])
            ->callAction('belgeIste', [
                'notlar' => [['alan' => 'evrak:ticaret_sicil_gazetesi', 'aciklama' => 'Güncel nüsha.']],
                'ek_talepler' => [],
                'sure_gun' => 7,
            ])
            ->assertHasNoActionErrors();

        $talep = $kurum->onayliKurumsalBasvuru()->acikBelgeTalebi();

        $this->assertNotNull($talep);
        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
    }

    /* ──────────────────── KURUM PANELİ VE YÜKLEME ──────────────────── */

    public function test_kurum_panelinde_talep_gorunur(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();
        $this->talepEt($kurum);

        $this->actingAs(User::where('email', 'kurumyetkili@ornek.test')->first());

        /*
         * 🪤 Metin KURUM ağzından olmalı. Şerit kişi ve kurum panellerinde
         * ORTAK bir sayfadan (BasvurumSayfasi) çiziliyor ve cümle kişi
         * ağzından yazılmıştı: kuruluşa "Basın kartınız geçerliliğini
         * koruyor" diyordu. Kuruluşun basın kartı yok.
         */
        $this->get('/kurum/basvurum')
            ->assertSuccessful()
            ->assertSee('Belge bekleniyor')
            ->assertSee('Kuruluşunuzun akreditasyonu geçerliliğini koruyor', false)
            ->assertDontSee('Basın kartınız geçerliliğini koruyor')
            ->assertSee('Ticaret Sicili Gazetesi');
    }

    /**
     * 💀 ASIL SÖZLEŞME: belge gelince kuruluşun akreditasyonu ve başvurunun
     * durumu YERİNDE kalır, yeniden inceleme açılmaz.
     */
    public function test_belge_yuklenince_akreditasyon_yerinde_kalir(): void
    {
        Storage::fake(config('bys.evrak_disk'));

        $this->actingAs($this->yetkili());
        $tur = $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();
        $this->talepEt($kurum);

        $basvuru = $kurum->onayliKurumsalBasvuru();
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh(), 'belge_talebi');

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('İstenen belgeyi gönderin')
            ->assertSee('Kuruluşunuzun akreditasyonu geçerliliğini koruyor', false);

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'evraklar' => [$tur->id => UploadedFile::fake()->image('gazete.jpg', 400, 300)],
        ])->assertRedirect(route('basvuru.gonderildi'));

        $basvuru->refresh();

        $this->assertSame(BasvuruDurumu::Onaylandi, $basvuru->durum);
        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
        $this->assertNull($basvuru->acikBelgeTalebi());
        $this->assertTrue($basvuru->evraklar()->where('evrak_turu_id', $tur->id)->exists());
    }

    /** Süresi geçen kurum talebi panoda KURUM DETAYINA götürmeli. */
    public function test_gecikmis_talep_panoda_kurum_detayina_goturur(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $kurum = $this->akrediteKurum();

        Notification::fake();
        $this->talepEt($kurum);

        $talep = $kurum->onayliKurumsalBasvuru()->acikBelgeTalebi();
        $talep->forceFill(['son_tarih' => now()->subDays(4)->startOfDay()])->save();

        $satir = DikkatGerektirenler::satirlar()
            ->firstWhere('sebep', 'Belge talebinin süresi geçti');

        $this->assertNotNull($satir);
        $this->assertStringContainsString('Kuruluş hâlâ akredite', $satir['ayrinti']);
        $this->assertStringContainsString("/yonetim/kurumlar/{$kurum->ulid}/detay", $satir['adres']);
        $this->assertSame('akredite', $kurum->fresh()->akreditasyon_durumu);
    }
}
