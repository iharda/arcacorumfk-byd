<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Uye\Pages\Duyurular;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Duyuru;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Medya merkezi içerik sayfaları -- panel içerik tasarım planı B2/B3.
 *
 * 🔒 Korunan kurallar:
 *   1. Yayında OLMAYAN içerik ne listede ne detayda görünür.
 *   2. İçerik yalnızca AKREDİTE kullanıcıya açıktır (menüyü gizlemek yetki
 *      değildir; adresi bilen de giremez).
 *   3. "Yeni" rozeti son bakış damgasına göre çıkar ve ziyaretten sonra düşer.
 */
class MedyaMerkeziTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Panel düzeni rollere bakıyor (panel seçimi menüsü).
        $this->seed(RolYetkiSeeder::class);
    }

    private function akrediteUye(): User
    {
        $kullanici = User::create([
            'name' => 'Akredite Üye',
            'email' => 'uye@ornek.test',
            'password' => bcrypt('x'),
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => $kullanici->email,
        ]);

        Akreditasyon::create([
            'basvuru_id' => $basvuru->id,
            'kullanici_id' => $kullanici->id,
            'kart_no' => '2026-K-0001',
            'yil' => 2026,
            'tur_kodu' => 'K',
            'sira' => 1,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);

        return $kullanici;
    }

    private function duyuru(string $baslik, bool $yayinda = true, ?string $yayinAt = null): Duyuru
    {
        return Duyuru::create([
            'baslik' => $baslik,
            'ozet' => $baslik.' özeti',
            'icerik' => '<p>'.$baslik.' gövdesi</p>',
            'yayinda' => $yayinda,
            'yayin_at' => $yayinAt ? now()->parse($yayinAt) : now()->subHour(),
        ]);
    }

    private function sayfa(User $kullanici)
    {
        Filament::setCurrentPanel('uye');

        return Livewire::actingAs($kullanici)->test(Duyurular::class);
    }

    public function test_yayinda_olmayan_duyuru_listede_yok(): void
    {
        $kullanici = $this->akrediteUye();
        $this->duyuru('Yayındaki duyuru');
        $this->duyuru('Taslak duyuru', yayinda: false);

        $this->sayfa($kullanici)
            ->assertSee('Yayındaki duyuru')
            ->assertDontSee('Taslak duyuru');
    }

    /**
     * 🔒 Adresi elle yazmak da işe yaramaz: yayında değilse kayıt bulunamaz.
     *
     * 🪤 Burada `assertNotFound()` kullanılamaz: `ModelNotFoundException` HTTP
     * katmanında 404'e çevrilir, Livewire birim testinde ise doğrudan
     * fırlatılır. Kuralın kendisi aynı; 404 gövdesi tarayıcıda doğrulandı.
     */
    public function test_yayinda_olmayan_duyurunun_detayi_acilmiyor(): void
    {
        $kullanici = $this->akrediteUye();
        $taslak = $this->duyuru('Taslak duyuru', yayinda: false);

        Filament::setCurrentPanel('uye');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($kullanici)
            ->withQueryParams(['acik' => $taslak->ulid])
            ->test(Duyurular::class);
    }

    /** Menüyü gizlemek yetki değildir: akreditasyonsuz kullanıcı 403 alır. */
    public function test_akreditasyonsuz_kullanici_sayfaya_giremiyor(): void
    {
        $kullanici = User::create([
            'name' => 'Akreditasyonsuz', 'email' => 'yok@ornek.test', 'password' => bcrypt('x'),
        ]);

        Filament::setCurrentPanel('uye');

        Livewire::actingAs($kullanici)->test(Duyurular::class)->assertForbidden();
    }

    public function test_arama_baslikta_suzuyor(): void
    {
        $kullanici = $this->akrediteUye();
        $this->duyuru('Kocaelispor maçı akreditasyonu');
        $this->duyuru('Transfer dönemi kapanışı');

        $this->sayfa($kullanici)
            ->set('arama', 'Kocaelispor')
            ->assertSee('Kocaelispor maçı akreditasyonu')
            ->assertDontSee('Transfer dönemi kapanışı');
    }

    /**
     * 🪤 Eşik ÖNCE okunur, damga SONRA güncellenir. Aksi hâlde kullanıcı
     * sayfayı açar açmaz her şey "okundu" olur ve rozet hiç görünmez.
     */
    public function test_yeni_rozeti_ziyaretten_sonra_dusuyor(): void
    {
        $kullanici = $this->akrediteUye();
        $kullanici->forceFill(['duyuru_gorulme_at' => now()->subDay()])->save();

        $this->duyuru('Eşikten sonraki duyuru', yayinAt: now()->subMinutes(10)->toDateTimeString());

        // İlk ziyarette rozet var...
        $this->sayfa($kullanici)->assertSee('Yeni');

        // ...ve damga güncellendiği için ikinci ziyarette yok.
        $this->assertTrue($kullanici->refresh()->duyuru_gorulme_at->isAfter(now()->subMinute()));
        $this->sayfa($kullanici->refresh())->assertDontSee('Yeni');
    }

    /** Hiç bakılmamışsa rozet çıkmaz: "son bakışından beri" diye bir an yok. */
    public function test_ilk_ziyarette_rozet_cikmiyor(): void
    {
        $kullanici = $this->akrediteUye();
        $this->duyuru('İlk duyuru');

        $this->sayfa($kullanici)->assertDontSee('Yeni');
    }

    /** Liste sayfasında ağır medya kurulmaz: `<video>` yalnızca detayda. */
    public function test_listede_video_elemani_kurulmuyor(): void
    {
        $kullanici = $this->akrediteUye();
        $duyuru = $this->duyuru('Videolu duyuru');
        $duyuru->update(['video_yolu' => 'duyuru/deneme.mp4']);

        $this->sayfa($kullanici)->assertDontSeeHtml('<video');
    }

    /** Detayda tam metin ve tek video vardır. */
    public function test_detayda_govde_ve_video_var(): void
    {
        $kullanici = $this->akrediteUye();
        $duyuru = $this->duyuru('Videolu duyuru');
        $duyuru->update(['video_yolu' => 'duyuru/deneme.mp4']);

        Filament::setCurrentPanel('uye');

        Livewire::actingAs($kullanici)
            ->withQueryParams(['acik' => $duyuru->ulid])
            ->test(Duyurular::class)
            ->assertSee('Videolu duyuru gövdesi', escape: false)
            ->assertSeeHtml('<video');
    }
}
