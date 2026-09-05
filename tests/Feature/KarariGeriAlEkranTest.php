<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\Pages\Inceleme;
use App\Filament\Yonetim\Resources\Basvurus\Pages\ListBasvurus;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Kararı geri al" EKRANDAN çağrıldığında kart askısı da işliyor mu?
 *
 * 🪤 Servis testleri (KarariGeriAlTest) akışı doğruluyor ama düğmeyi
 * akışa BAĞLAYAN yolu değil: form alanının adı (`kartlari_askiya_al`)
 * servisin parametresine ulaşmazsa yetkili kutuyu işaretler, hiçbir şey olmaz
 * ve kimse fark etmez. Burada eylem gerçekten çağrılıyor.
 *
 * ⚠️ Kip GÖVDESİ Livewire testinde render EDİLMİYOR (mevcut "Gerekçe" alanı
 * da görünmüyor); bu yüzden HTML'de alan aramak yerine eylem çalıştırılıp
 * SONUCUNA bakılıyor.
 */
class KarariGeriAlEkranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
        $this->actingAs($this->yetkili());
    }

    private function yetkili(): User
    {
        $u = User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_SUPER);
        $u->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $u->fresh();
    }

    /** Akredite kurum + onaylı kurumsal başvuru + bir çalışanın aktif kartı. */
    private function senaryo(): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-BV-0044',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $calisan = User::create([
            'name' => 'Muhabir', 'email' => 'muhabir@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);

        $calisanBasvurusu = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'kullanici_id' => $calisan->id,
            'basvuran_eposta' => $calisan->email,
        ]);

        $kart = Akreditasyon::create([
            'kullanici_id' => $calisan->id,
            'basvuru_id' => $calisanBasvurusu->id,
            'kurum_id' => $kurum->id,
            'kart_no' => '2026-BS-0001',
            'yil' => 2026,
            'tur_kodu' => 'BS',
            'sira' => 1,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        return [$basvuru, $kurum, $kart];
    }

    /** Yetkiliye gösterilecek sayı: kaç çalışan kartı etkilenecek. */
    public function test_inceleme_ekrani_etkilenen_kart_sayisini_bilir(): void
    {
        [$basvuru, $kurum] = $this->senaryo();

        $sayfa = Livewire::test(Inceleme::class, ['record' => $basvuru->ulid]);

        $this->assertSame(1, $sayfa->instance()->etkilenenKartSayisi());

        // Kurum akredite değilse geri alma kartlara zaten dokunmaz: sayı sıfır.
        $kurum->update(['akreditasyon_durumu' => 'beklemede']);

        $this->assertSame(
            0,
            Livewire::test(Inceleme::class, ['record' => $basvuru->ulid])
                ->instance()->etkilenenKartSayisi(),
        );
    }

    public function test_inceleme_ekranindan_geri_alinca_kart_askiya_alinir(): void
    {
        [$basvuru, $kurum, $kart] = $this->senaryo();

        Livewire::test(Inceleme::class, ['record' => $basvuru->ulid])
            ->callAction('karariGeriAl', [
                'gerekce' => 'Yanlış onayladım.',
                'kartlari_askiya_al' => true,
            ]);

        $this->assertSame(BasvuruDurumu::Incelemede, $basvuru->fresh()->durum);
        $this->assertSame('beklemede', $kurum->fresh()->akreditasyon_durumu);
        $this->assertSame(AkreditasyonDurumu::Askida, $kart->fresh()->durum);
    }

    /** İşaretlenmezse kart durur -- kutu gerçekten bir seçim olmalı. */
    public function test_kutu_isaretlenmezse_kart_aktif_kalir(): void
    {
        [$basvuru, , $kart] = $this->senaryo();

        Livewire::test(Inceleme::class, ['record' => $basvuru->ulid])
            ->callAction('karariGeriAl', [
                'gerekce' => 'Yanlış onayladım.',
                'kartlari_askiya_al' => false,
            ]);

        $this->assertSame(AkreditasyonDurumu::Aktif, $kart->fresh()->durum);
    }

    /** Aynı karar LİSTE satırından da veriliyor; oradaki kutu da bağlı olmalı. */
    public function test_liste_satirindan_geri_alinca_da_kart_askiya_alinir(): void
    {
        [$basvuru, $kurum, $kart] = $this->senaryo();

        Livewire::test(ListBasvurus::class)
            ->callTableAction('karariGeriAl', $basvuru->getKey(), [
                'gerekce' => 'Yanlış onayladım.',
                'kartlari_askiya_al' => true,
            ]);

        $this->assertSame('beklemede', $kurum->fresh()->akreditasyon_durumu);
        $this->assertSame(AkreditasyonDurumu::Askida, $kart->fresh()->durum);
    }
}
