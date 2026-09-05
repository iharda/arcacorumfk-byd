<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Kurum\Pages\Basvurum;
use App\Filament\Yonetim\Widgets\DikkatGerektirenler;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\BasvuruBileti;
use App\Models\BasvuruDuzeltmesi;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Eksik evrak: kurumun kendi panelinden görmesi ve yüklemesi (6),
 * gecikirse yöneticinin uyarılması (7).
 *
 * 💀 Kuruluşun belge yükleyebildiği TEK yol e-postayla giden tek kullanımlık
 * bağlantıydı. Posta silinmişse ya da başka birine gitmişse kuruluş çıkmaz
 * sokaktaydı: kulüp bekliyor, kuruluş yükleyemiyor, kimse fark etmiyor.
 */
class EksikEvrakPaneldeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->widgetOnbelleginiTemizle();
    }

    /**
     * 🪤 `DikkatGerektirenler::$onbellek` STATİK: aynı süreçteki ikinci test
     * birincinin satırlarını görür ve eşik testleri sahte geçer.
     * (PanoTest'teki kalıbın aynısı.)
     */
    private function widgetOnbelleginiTemizle(): void
    {
        $ozellik = new \ReflectionProperty(DikkatGerektirenler::class, 'onbellek');
        $ozellik->setValue(null, null);
    }

    /** Belge beklenen kurumsal başvuru + panele girebilen kurum yetkilisi. */
    private function senaryo(int $gunOnce = 9): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $yetkili = User::create([
            'name' => 'Kurum Yetkilisi', 'email' => 'kurum@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);
        $yetkili->assignRole(User::ROL_KURUM);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::EksikEvrak,
            'kurum_id' => $kurum->id,
            'kullanici_id' => $yetkili->id,
            'basvuru_no' => '2026-BV-0070',
            'basvuran_eposta' => $yetkili->email,
            'duzeltme_notlari' => ['veri:vergi_no' => 'Vergi numarası okunmuyor.'],
        ]);

        BasvuruDuzeltmesi::create([
            'basvuru_id' => $basvuru->id,
            'sira' => 1,
            'talep_notlari' => ['veri:vergi_no' => 'Vergi numarası okunmuyor.'],
            'talep_at' => now()->subDays($gunOnce),
        ]);

        return [$basvuru, $kurum, $yetkili->fresh()];
    }

    /* ─────────── 6) Kurum paneli ─────────── */

    public function test_panelde_uyari_ve_istenen_kalem_gorunur(): void
    {
        [, , $yetkili] = $this->senaryo();

        Livewire::actingAs($yetkili)->test(Basvurum::class)
            ->assertSee('Eksik evrak bekleniyor')
            ->assertSee('Eksik evrağı yükle');
    }

    /** 🔑 Asıl eksik buydu: panelden yükleme yoluna girilebilmeli. */
    public function test_panelden_yukleme_sayfasina_gidilir(): void
    {
        [$basvuru, , $yetkili] = $this->senaryo();

        $this->assertSame(0, BasvuruBileti::count());

        Livewire::actingAs($yetkili)->test(Basvurum::class)
            ->callAction('eksikEvrak')
            ->assertRedirectContains('/basvuru/duzelt/');

        // Bilet KENDİ başvurusuna üretilmiş olmalı.
        $bilet = BasvuruBileti::sole();
        $this->assertSame($basvuru->id, $bilet->basvuru_id);
        $this->assertNull($bilet->kullanildi_at);
    }

    /** Belge beklenmiyorsa ne uyarı ne düğme çıkar. */
    public function test_belge_beklenmiyorsa_dugme_yok(): void
    {
        [$basvuru, , $yetkili] = $this->senaryo();
        $basvuru->forceFill(['durum' => BasvuruDurumu::Onaylandi, 'duzeltme_notlari' => null])->save();

        Livewire::actingAs($yetkili)->test(Basvurum::class)
            ->assertDontSee('Eksik evrak bekleniyor')
            ->assertActionHidden('eksikEvrak');
    }

    /* ─────────── 7) Yönetici uyarısı ─────────── */

    public function test_gecikmis_eksik_evrak_panoda_uyarir(): void
    {
        $this->senaryo(gunOnce: 9);   // varsayılan eşik 7 gün

        $satirlar = DikkatGerektirenler::satirlar();

        $this->assertTrue($satirlar->contains('sebep', 'Eksik evrak gecikti'));
        $this->assertStringContainsString(
            '9 gündür yüklenmedi',
            $satirlar->firstWhere('sebep', 'Eksik evrak gecikti')['ayrinti'],
        );
    }

    /** Eşiğin altındaki başvuru listeye düşmemeli. */
    public function test_esik_altinda_uyari_yok(): void
    {
        $this->senaryo(gunOnce: 2);

        $this->assertFalse(DikkatGerektirenler::satirlar()->contains('sebep', 'Eksik evrak gecikti'));
    }

    /** 0 = uyarma. Kulüp bu listeyi kapatabilmeli. */
    public function test_sifir_gun_uyariyi_kapatir(): void
    {
        Ayar::yaz('eksik_evrak_uyari_gun', 0);
        $this->senaryo(gunOnce: 40);

        $this->assertFalse(DikkatGerektirenler::satirlar()->contains('sebep', 'Eksik evrak gecikti'));
    }

    /** 🪤 Yanıtlanmış tur bekleyen sayılmaz: belge gelmiş, iş bitmiş. */
    public function test_yanitlanmis_tur_uyari_dogurmaz(): void
    {
        [$basvuru] = $this->senaryo(gunOnce: 30);
        $basvuru->duzeltmeler()->update(['yanit_at' => now()]);

        $this->assertFalse(DikkatGerektirenler::satirlar()->contains('sebep', 'Eksik evrak gecikti'));
    }
}
