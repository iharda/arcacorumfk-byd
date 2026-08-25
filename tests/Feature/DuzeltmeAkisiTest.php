<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\CalisanAraligi;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Models\Kurum;
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
            ->assertSee('Başvuru geçmişi')
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

    /** 🪤 Ek talebin etiketi ŞEMADA yok, TURDA duruyor: ham "ek:1" gösterilmesin. */
    public function test_ek_talep_etiketi_ham_anahtar_gostermez(): void
    {
        $basvuru = $this->basvuru();

        app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, [], null, [[
            'anahtar' => DuzeltmeAlanlari::EK_ONEK.'1',
            'etiket' => 'Yayın sözleşmesi',
            'tip' => 'dosya',
            'aciklama' => 'İlk sayfa yeterli',
        ]]);

        $this->assertSame('Yayın sözleşmesi',
            $basvuru->fresh()->duzeltmeEtiketi('ek:1'));

        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Yayın sözleşmesi')
            ->assertDontSee('ek:1');
    }

    /**
     * 🔑 Yusuf md.4: "kullanıcının NEYİ YENİ EKLEDİĞİNİ" göster. Yüklenen
     * evrak tura kaydedilmiyordu; geçmişte "istendi" görünüyor ama başvuranın
     * gerçekten yükleyip yüklemediği görünmüyordu.
     */
    public function test_yuklenen_evrak_tura_kaydedilir(): void
    {
        $basvuru = $this->basvuru();
        $this->zorunluEvraklariYukle($basvuru);

        $tur = EvrakTuru::turIcin($basvuru->tur)->firstWhere('zorunlu', true);
        $onceki = $basvuru->fresh()->evraklar->firstWhere('evrak_turu_id', $tur->id);

        $duzeltme = app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, [
            DuzeltmeAlanlari::EVRAK_ONEK.$tur->kod => 'Okunmuyor, yeniden yükleyin',
        ]);

        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        // 🪤 Laravel testinde dosya AYRI bir parametre değil, veri dizisinde.
        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'evraklar' => [$tur->id => UploadedFile::fake()->image('yeni-belge.jpg', 400, 400)],
        ]);

        $degisim = $duzeltme->fresh()->degisiklikler[DuzeltmeAlanlari::EVRAK_ONEK.$tur->kod] ?? null;

        $this->assertNotNull($degisim, 'Yüklenen evrak tura kaydedilmedi.');
        $this->assertSame('yeni-belge.jpg', $degisim['yeni']);
        $this->assertSame($onceki->orijinal_ad, $degisim['eski'],
            'Önceki dosyanın adı yükleme ÖNCESİ okunmalı; yukle() onu arşivliyor.');
    }

    /**
     * 🔑 Yusuf md.4: çizelge "ilk bilgiler"den başlar. Ayrı anlık görüntü
     * saklanmıyor; ilk değerler turların `eski` alanından çözülüyor.
     */
    public function test_ilk_bilgiler_turlardan_cozuluyor(): void
    {
        $basvuru = $this->basvuru();
        $this->zorunluEvraklariYukle($basvuru);
        $akis = app(BasvuruAkisi::class);

        // 1. tur: adres değişti
        $akis->eksikEvrakIste($basvuru, ['veri:adres' => 'Adres eksik']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());
        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]),
            ['alan' => ['veri_adres' => 'İkinci adres']]);

        // 2. tur: adres TEKRAR değişti
        $basvuru->refresh()->update(['durum' => BasvuruDurumu::Incelemede]);
        $akis->eksikEvrakIste($basvuru->fresh(), ['veri:adres' => 'Hâlâ eksik']);
        $token2 = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());
        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token2]),
            ['alan' => ['veri_adres' => 'Üçüncü adres']]);

        $ilk = $basvuru->fresh()->ilkDegerler();

        // İlk değer BİRİNCİ turun `eski`si -- ikincinin değil.
        $this->assertSame('Eski adres 1', $ilk['veri:adres']);
        // Hiç değişmemiş alan bugünkü hâliyle aynı.
        $this->assertSame('Aday Kişi', $ilk['veri:ad_soyad']);
    }

    /** 🪤 "İlk bilgiler" İLK turda da görünmeli; önceki tur şartına bağlı değil. */
    public function test_ilk_bilgiler_ilk_turda_da_gorunur(): void
    {
        $basvuru = $this->basvuru();
        app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, ['veri:adres' => 'Adres eksik']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Başvuru geçmişi')
            ->assertSee('İlk bilgiler')
            ->assertSee('Eski adres 1');
    }

    /**
     * 💥 KURUMSAL yol test edilmiyordu ve orada patlıyordu:
     * `Kurum::calisan_araligi` modelde ENUM'a cast ediliyor, biçimlendirici
     * ise `(string) $deger` yapıyordu → "could not be converted to string",
     * düzeltme sayfası ve inceleme ekranı komple 500.
     *
     * 💀 Ancak "İlk bilgiler" TÜM alanları okumaya başlayınca ortaya çıktı.
     */
    public function test_kurumsal_basvuruda_tum_alanlar_gosterilebiliyor(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Test Gazetesi',
            'adres' => 'Eski adres 1',
            'il' => 'Çorum',
            'ilce' => 'Merkez',
            'telefon' => '+903642223344',
            'eposta' => 'kurum@ornek.test',
            'vergi_dairesi' => 'Çorum',
            'vergi_no' => '1234567890',
            'calisan_araligi' => CalisanAraligi::Bes,
            'yayin_platformlari' => [['ad' => 'Site', 'url' => 'https://ornek.test']],
            'akreditasyon_durumu' => 'beklemede',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Incelemede,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Yetkili Kişi',
            'basvuran_eposta' => 'yetkili-kurum@ornek.test',
            'basvuran_telefon' => '+905321112233',
        ]);

        // Her alan tek tek gösterilebilmeli -- hiçbiri istisna atmamalı.
        foreach (array_keys(DuzeltmeAlanlari::veriTanimlari(BasvuruTuru::Kurum)) as $anahtar) {
            $this->assertIsString($basvuru->duzeltmeDegeriGoster($anahtar, null),
                "Gösterilemedi: {$anahtar}");
        }

        $this->assertSame('1-5 kişi', $basvuru->duzeltmeDegeriGoster('veri:calisan_araligi', null));

        // Ve sayfa gerçekten açılmalı.
        app(BasvuruAkisi::class)->eksikEvrakIste($basvuru, ['veri:vergi_no' => 'Numara hatalı']);
        $token = app(BasvuruBiletiAkisi::class)->uret($basvuru->fresh());

        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('İlk bilgiler')
            ->assertSee('1-5 kişi');
    }
}
