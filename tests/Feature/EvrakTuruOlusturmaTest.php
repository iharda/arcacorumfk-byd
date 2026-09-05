<?php

namespace Tests\Feature;

use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\EvrakTurleri\EvrakTuruResource;
use App\Filament\Yonetim\Resources\EvrakTurleri\Pages\EvrakTuruOlustur;
use App\Filament\Yonetim\Resources\EvrakTurleri\Pages\ListEvrakTurleri;
use App\Models\EvrakTuru;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Yeni evrak türü oluşturma -- İbrahim Bey, 05.09.2026.
 *
 * 💀 "Yeni evrak türü" düğmesi ALANSIZ bir modal açıyordu: `CreateAction`
 * çıplaktı, oluşturma sayfası `create` değil `olustur` anahtarıyla kayıtlıydı
 * ve kaynakta `form()` yoktu. Doğrulanacak alan olmadığı için gönderim
 * doğrudan `insert into evrak_turleri (created_at, updated_at)` çalıştırıyor,
 * veritabanı `kod` NOT NULL diye reddediyordu. Ekranda hiçbir açıklama yok,
 * kayıt da oluşmuyordu.
 */
class EvrakTuruOlusturmaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);

        $u = User::create([
            'name' => 'Süper', 'email' => 'super@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true, 'email_verified_at' => now(),
        ]);
        $u->assignRole(User::ROL_SUPER);
        $this->actingAs($u->fresh());
    }

    public function test_liste_sayfasi_ve_dugme(): void
    {
        $this->get('/yonetim/evrak-turleri')->assertSuccessful()->assertSee('Yeni evrak türü');
        Livewire::test(ListEvrakTurleri::class)->assertActionVisible('create');
    }

    /**
     * 🔑 ASIL KORUMA: düğme MODAL AÇMAZ, oluşturma sayfasına götürür.
     * Modal açsaydı (şemasız hâliyle) boş satır insert'i geri gelirdi; şema
     * takılsaydı da `EvrakTuruOlustur::afterCreate()` denetim kaydı atlanırdı.
     */
    public function test_dugme_olusturma_sayfasina_goturur(): void
    {
        // Düğme bir BAĞLANTI olmalı: sayfada oluşturma adresi geçiyorsa modal
        // değil, `EvrakTuruOlustur` sayfası açılıyor demektir.
        $this->get('/yonetim/evrak-turleri')
            ->assertSuccessful()
            ->assertSee(EvrakTuruResource::getUrl('olustur'), escape: false);
    }

    /**
     * 💀 Regresyon: alansız gönderimde veritabanına BOŞ SATIR gitmemeli.
     * Eski davranışta doğrulama hiç çalışmıyor, insert veritabanı seviyesinde
     * patlıyordu.
     */
    public function test_bos_gonderim_kayit_olusturmaz(): void
    {
        // 🪤 Tablo boş DEĞİL: `imza_sirkuleri` bir migration'la geliyor.
        // Ölçülen şey mutlak sayı değil, DEĞİŞMEMİŞ olması.
        $once = EvrakTuru::count();

        Livewire::test(EvrakTuruOlustur::class)
            ->call('create')
            ->assertHasFormErrors(['ad', 'kod', 'basvuru_turleri', 'izinli_formatlar']);

        $this->assertSame($once, EvrakTuru::count());
        $this->assertSame(0, EvrakTuru::whereNull('kod')->count(), 'Kodsuz boş satır oluştu.');
    }

    /** Oluşturma denetime yazılır: bu ekran kamuya açık formu değiştiriyor. */
    public function test_olusturma_denetime_yazilir(): void
    {
        Livewire::test(EvrakTuruOlustur::class)
            ->fillForm([
                'ad' => 'Yayın sözleşmesi',
                'kod' => 'yayin_sozlesmesi',
                'basvuru_turleri' => [BasvuruTuru::Kurum->value],
                'izinli_formatlar' => ['pdf'],
                'maks_boyut_kb' => 8192,
                'sira' => 100,
                'aktif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('denetim_kaydi', ['olay' => 'evrak_turu.eklendi']);
    }

    public function test_olusturma_sayfasi_acilir(): void
    {
        $this->get('/yonetim/evrak-turleri/olustur')->assertSuccessful();
    }

    public function test_yeni_evrak_turu_kaydedilir(): void
    {
        Livewire::test(EvrakTuruOlustur::class)
            ->fillForm([
                'ad' => 'Yayın sözleşmesi',
                'kod' => 'yayin_sozlesmesi',
                'aciklama' => 'Kurumla imzalı sözleşme.',
                'basvuru_turleri' => [BasvuruTuru::Kurum->value],
                'izinli_formatlar' => ['pdf'],
                'maks_boyut_kb' => 8192,
                'zorunlu' => false,
                'hassas' => false,
                'sira' => 100,
                'aktif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('evrak_turleri', ['kod' => 'yayin_sozlesmesi']);
    }
}
