<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\EvrakYukleyici;
use App\Support\DuzeltmeAlanlari;
use Database\Seeders\AyarSeeder;
use Database\Seeders\EvrakTuruSeeder;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Düzeltme akışı uçtan uca -- Yusuf revizyonu 25.08.2026.
 *
 * 💀 Eskiden işaretli VERİ alanları yalnızca listeleniyordu; başvuran serbest
 * metin yazıyor, yanlış veri yanlış kalıyordu. Ayrıca her tur bir öncekinin
 * üstüne yazıldığı için "ne istenmişti, ne değişti" hiçbir yerde durmuyordu.
 */
class DuzeltmeAkisiTest extends TestCase
{
    use RefreshDatabase;

    private User $yetkili;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        $this->seed(EvrakTuruSeeder::class);
        $this->seed(AyarSeeder::class);
        Notification::fake();

        $this->yetkili = User::create([
            'name' => 'Kulüp Yetkilisi',
            'email' => 'yetkili@ornek.test',
            'password' => bcrypt('x'),
        ]);
        $this->yetkili->assignRole(User::ROL_YETKILI);
        $this->actingAs($this->yetkili);
    }

    /**
     * Zorunlu evraklari yukler: yoksa `gonder()` "Eksik zorunlu evrak" der ve
     * duzeltme akisinin mutlu yolu hic calismaz.
     */
    private function zorunluEvraklariYukle(Basvuru $basvuru): void
    {
        Storage::fake(config('byd.evrak_disk'));

        foreach (EvrakTuru::turIcin($basvuru->tur)->where('zorunlu', true) as $tur) {
            app(EvrakYukleyici::class)->yukle(
                $basvuru, $tur, UploadedFile::fake()->image('belge.jpg', 300, 300),
            );
        }
    }

    private function basvuru(): Basvuru
    {
        return Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Incelemede,
            'basvuran_ad' => 'Aday Kişi',
            'basvuran_eposta' => 'aday@ornek.test',
            'basvuran_telefon' => '+905321112233',
            'form_verisi' => ['adres' => 'Eski adres 1', 'il' => 'Çorum', 'ilce' => 'Merkez'],
        ]);
    }

    /** İşaretlenen veri alanı GERÇEKTEN düzelir ve öncesi/sonrası kayda geçer. */
    public function test_isaretli_alan_duzeltilir_ve_oncesi_sonrasi_kaydedilir(): void
    {
        $basvuru = $this->basvuru();
        $this->zorunluEvraklariYukle($basvuru);

        $duzeltme = app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, [
            'veri:telefon' => 'Numaraya ulaşılamıyor',
            'veri:adres' => 'Adres eksik',
        ]);

        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Düzeltilecek bilgiler')
            ->assertSee('Düzeltme talebi 01')
            // Şu anki değer ekranda görünmeli.
            ->assertSee('Eski adres 1');

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'alan' => [
                'veri_telefon' => '532 999 88 77',
                'veri_telefon_ulke' => '+90',
                'veri_adres' => 'Yeni adres 42',
            ],
            'aciklama' => 'Bilgileri güncelledim',
        ])->assertRedirect(route('basvuru.gonderildi'));

        $basvuru->refresh();

        $this->assertSame('+905329998877', $basvuru->basvuran_telefon, 'Telefon düzelmedi.');
        $this->assertSame('Yeni adres 42', $basvuru->form_verisi['adres'], 'Adres düzelmedi.');

        $duzeltme->refresh();

        $this->assertNotNull($duzeltme->yanit_at);
        $this->assertSame('Bilgileri güncelledim', $duzeltme->yanit_aciklama);
        $this->assertSame('+905321112233', $duzeltme->degisiklikler['veri:telefon']['eski']);
        $this->assertSame('+905329998877', $duzeltme->degisiklikler['veri:telefon']['yeni']);
        $this->assertSame('Eski adres 1', $duzeltme->degisiklikler['veri:adres']['eski']);
    }

    /** İkinci tur birincinin üstüne YAZMAZ; geçmiş durur. */
    public function test_ikinci_tur_gecmisi_silmez(): void
    {
        $basvuru = $this->basvuru();
        $this->zorunluEvraklariYukle($basvuru);
        $akis = app(BasvuruAkisi::class);

        $birinci = $akis->eksikEvrakIste($basvuru, ['veri:adres' => 'Adres eksik']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'alan' => ['veri_adres' => 'İkinci adres'],
        ]);

        $basvuru->refresh()->update(['durum' => BasvuruDurumu::Incelemede]);

        $ikinci = $akis->eksikEvrakIste($basvuru->fresh(), ['veri:ad_soyad' => 'Ad soyad eksik']);

        $this->assertSame(1, $birinci->sira);
        $this->assertSame(2, $ikinci->sira);
        $this->assertSame(2, BasvuruDuzeltmesi::where('basvuru_id', $basvuru->id)->count());

        // Birinci turun kaydı DURUYOR.
        $this->assertSame('Eski adres 1', $birinci->fresh()->degisiklikler['veri:adres']['eski']);

        $token2 = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token2]))
            ->assertOk()
            ->assertSee('Düzeltme talebi 02')
            ->assertSee('Önceki düzeltmeler')
            ->assertSee('İkinci adres');
    }

    /** 🔒 İşaretlenmemiş alan yazılamaz -- sessizce yok sayılmaz da. */
    public function test_isaretlenmemis_alan_yazilamaz(): void
    {
        $basvuru = $this->basvuru();
        app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, ['veri:adres' => 'Adres eksik']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'alan' => [
                'veri_adres' => 'Yeni adres',
                'veri_ad_soyad' => 'Sahte İsim',   // işaretlenmemişti
            ],
        ])->assertSessionHasErrors('genel');

        $this->assertSame('Aday Kişi', $basvuru->fresh()->basvuran_ad, 'İşaretsiz alan yazıldı!');
    }

    /** 🔒 E-posta düzeltilemez: bilet o adrese gidiyor, kimlik bağı orada. */
    public function test_eposta_duzeltilemez_yalnizca_not(): void
    {
        $basvuru = $this->basvuru();
        app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, ['veri:eposta' => 'Adres hatalı görünüyor']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $yanit = $this->get(route('basvuru.duzelt', ['token' => $token]))->assertOk();

        $yanit->assertSee('Açıklamanız');
        $yanit->assertDontSee('name="alan[veri_eposta]"', false);
    }

    /** Listede olmayan ek talep: kendi başlığıyla yazılı bilgi istenebilir. */
    public function test_ek_talep_yazili_bilgi(): void
    {
        $basvuru = $this->basvuru();

        $duzeltme = app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, [], null, [[
            'anahtar' => DuzeltmeAlanlari::EK_ONEK.'1',
            'etiket' => 'Yayın sözleşmesi bilgisi',
            'tip' => 'metin',
            'aciklama' => 'Sözleşme numarasını yazın',
        ]]);

        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Ek talepler')
            ->assertSee('Yayın sözleşmesi bilgisi');

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'ek' => ['ek_1' => 'SZL-2026-42'],
        ]);

        $this->assertSame('SZL-2026-42', $duzeltme->fresh()->degisiklikler['ek:1']['yeni']);
    }

    /**
     * 💀 Gönderim başarısız olsa bile (zorunlu evrak eksik) düzeltilen alanlar
     * KAYBOLMAZ ve tur AÇIK kalır -- kişi aynı bağlantıdan devam edebilir.
     */
    public function test_gonderim_basarisizsa_degisiklik_korunur_tur_acik_kalir(): void
    {
        $basvuru = $this->basvuru();   // zorunlu evrak YOK: gonder() patlayacak

        $duzeltme = app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, ['veri:adres' => 'Adres eksik']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'alan' => ['veri_adres' => 'Kurtarılan adres'],
        ])->assertSessionHasErrors('genel');

        $this->assertSame('Kurtarılan adres', $basvuru->fresh()->form_verisi['adres'],
            'Gönderim patlayınca düzeltme de kayboldu.');

        $duzeltme->refresh();

        $this->assertNull($duzeltme->yanit_at, 'Tur kapandı; başvuran devam edemez.');
        $this->assertSame('Kurtarılan adres', $duzeltme->degisiklikler['veri:adres']['yeni']);
        $this->assertSame('Eski adres 1', $duzeltme->degisiklikler['veri:adres']['eski']);

        // Aynı bağlantı hâlâ çalışıyor ve tur başlığı duruyor.
        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Düzeltme talebi 01');
    }
}
