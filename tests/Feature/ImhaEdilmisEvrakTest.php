<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Servisler\EvrakYukleyici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * İmha edilmiş evrak -- Tutarsızlık incelemesi M2.2.
 *
 * 💀 Asıl hata: `bys:evrak-imha` dosyayı siler, KAYDI bırakır. Hiçbir ekran
 * `yol === null` durumunu sormuyordu; evrak düğmesi normal çiziliyor, tıklayan
 * yetkili `Storage::get(null)` üzerinden 500 alıyordu. `kimlik_gorseli` türünde
 * `imha_gun = 180` -- yani HER başvurunun kimlik belgesi, akreditasyon hâlâ
 * aktifken bu tuzağa düşecekti. Sezonun ikinci yarısında ortaya çıkardı.
 *
 * 🔒 Korunan davranış: imha edilmiş evrak 500 değil, ne olduğunu söyleyen bir
 * cevap verir; kayıt ve karar geçmişi yerinde durur.
 */
class ImhaEdilmisEvrakTest extends TestCase
{
    use RefreshDatabase;

    private function evrakTuru(int $imhaGun = 180): EvrakTuru
    {
        return EvrakTuru::create([
            'kod' => 'kimlik_gorseli',
            'ad' => 'Kimlik belgesi',
            'basvuru_turleri' => [BasvuruTuru::BasinMensubu->value],
            'zorunlu' => true,
            'izinli_formatlar' => ['pdf', 'jpg'],
            'maks_boyut_kb' => 8192,
            'hassas' => true,
            'imha_gun' => $imhaGun,
            'sira' => 40,
            'aktif' => true,
        ]);
    }

    /** @return array{User, Basvuru, Evrak} */
    private function evrakKur(?string $yol = 'basvuru/x/y.jpg'): array
    {
        $kullanici = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test', 'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => 'aday@ornek.test',
        ]);

        $evrak = Evrak::create([
            'basvuru_id' => $basvuru->id,
            'evrak_turu_id' => $this->evrakTuru()->id,
            'disk' => 'evrak',
            'yol' => $yol,
            'orijinal_ad' => 'kimlik.jpg',
            'mime' => 'image/jpeg',
            'boyut' => 1024,
            'sifreli' => false,
        ]);

        return [$kullanici, $basvuru, $evrak];
    }

    /** 💀 Asıl hata buydu: 500 yerine "bu dosya artık yok" diyen bir cevap. */
    public function test_imha_edilmis_evrak_410_doner(): void
    {
        [$kullanici, , $evrak] = $this->evrakKur(yol: null);

        $this->actingAs($kullanici)
            ->get(route('evrak.goster', $evrak))
            ->assertStatus(410);
    }

    /** Dosyası duran evrak eskisi gibi açılmaya devam etmeli. */
    public function test_duran_evrak_hala_acilabiliyor(): void
    {
        Storage::fake('evrak');
        Storage::disk('evrak')->put('basvuru/x/y.jpg', 'icerik');

        [$kullanici, , $evrak] = $this->evrakKur();

        $this->actingAs($kullanici)
            ->get(route('evrak.goster', $evrak))
            ->assertOk();
    }

    /**
     * 🔒 410 yetki kontrolünden SONRA gelmeli: yetkisiz kişi "bu evrak imha
     * edilmiş" bilgisini de almamalı. Aksi hâlde 410/403 farkı bir kaydın
     * varlığını sızdırır.
     */
    public function test_yetkisiz_kullanici_imha_bilgisini_de_goremez(): void
    {
        [, , $evrak] = $this->evrakKur(yol: null);

        $yabanci = User::create([
            'name' => 'Yabancı', 'email' => 'yabanci@ornek.test', 'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $this->actingAs($yabanci)
            ->get(route('evrak.goster', $evrak))
            ->assertForbidden();
    }

    /** İkinci kapı: doğrudan servise gidilirse de sebebi söyleyen bir hata çıkar. */
    public function test_yukleyici_imha_edilmis_evrakta_anlamli_hata_verir(): void
    {
        [, , $evrak] = $this->evrakKur(yol: null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('imha edilmiş');

        app(EvrakYukleyici::class)->icerik($evrak);
    }

    /** İmha komutu imha ANINI yazmalı; ekran tarihi oradan okuyor. */
    public function test_imha_komutu_imha_anini_kaydeder(): void
    {
        Storage::fake('evrak');
        Storage::disk('evrak')->put('basvuru/x/y.jpg', 'icerik');

        [, , $evrak] = $this->evrakKur();
        $evrak->forceFill(['imha_tarihi' => now()->subDay()])->save();

        $this->artisan('bys:evrak-imha')->assertSuccessful();

        $evrak->refresh();

        $this->assertTrue($evrak->imhaEdildiMi(), 'Dosya imha edilmiş sayılmalı.');
        $this->assertNotNull($evrak->imha_edildi_at, 'İmha anı kaydedilmeli.');
        $this->assertFalse(Storage::disk('evrak')->exists('basvuru/x/y.jpg'));

        // Kayıt DURUYOR: hangi belgenin imha edildiği hâlâ sorulabilmeli.
        $this->assertDatabaseHas('evraklar', ['id' => $evrak->id, 'orijinal_ad' => 'kimlik.jpg']);
    }
}
