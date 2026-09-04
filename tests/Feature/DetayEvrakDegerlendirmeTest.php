<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Filament\Yonetim\Resources\Kullanicilar\KullaniciResource;
use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kurum ve Kullanıcı detayında evrak + değerlendirme -- Tutarsızlık
 * incelemesi M2 (Sprint 2 kabul ölçütü).
 *
 * 💀 Onaylanmış bir kurumun Ticaret Sicili Gazetesi'ne ulaşmanın tek yolu
 * Kurumlar → detay → Başvuru geçmişi → numaraya tıkla → inceleme ekranı idi.
 * Kurumsal onayda akreditasyon kaydı doğmadığı için (AkreditasyonAkisi:33)
 * bu evrakların başka bir evi de yoktu.
 *
 * 🔒 İkinci koruma: değerlendirme sekmesi YETKİYE bağlı. Puan ve not kulüp
 * dışına çıkmaz; yetkisiz kullanıcı sekmeyi hiç görmemeli.
 */
class DetayEvrakDegerlendirmeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function yetkili(): User
    {
        $u = User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_YETKILI);

        return $u;
    }

    /**
     * ⚠️ Kullanıcı detayı `kullanici.yonet` ister ve o yetki BİLEREK yalnızca
     * super'de (RolYetkiSeeder: "Kullanici/rol yonetimi ... super'de").
     */
    private function super(): User
    {
        $u = User::create([
            'name' => 'Süper', 'email' => 'super@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_SUPER);

        return $u;
    }

    private function evrakTuru(): EvrakTuru
    {
        return EvrakTuru::create([
            'kod' => 'ticaret_sicil_gazetesi',
            'ad' => 'Ticaret Sicili Gazetesi',
            'basvuru_turleri' => [BasvuruTuru::Kurum->value],
            'zorunlu' => true,
            'izinli_formatlar' => ['pdf'],
            'maks_boyut_kb' => 8192,
            'hassas' => false,
            'sira' => 10,
            'aktif' => true,
        ]);
    }

    private function evrak(Basvuru $basvuru, ?string $ekEtiket = null): Evrak
    {
        return Evrak::create([
            'basvuru_id' => $basvuru->id,
            'evrak_turu_id' => $this->evrakTuru()->id,
            'disk' => 'evrak',
            'yol' => 'basvuru/x/'.uniqid().'.pdf',
            'orijinal_ad' => 'sicil.pdf',
            'mime' => 'application/pdf',
            'boyut' => 2048,
            'sifreli' => false,
            'ek_etiket' => $ekEtiket,
        ]);
    }

    /** 💀 Asıl eksik: onaylanmış kurumun evrakı detayda görünmüyordu. */
    public function test_kurum_detayinda_onaylanmis_basvurunun_evraklari_gorunur(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-KV-0001',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $this->evrak($basvuru);

        $this->actingAs($this->yetkili())
            ->get(KurumResource::getUrl('detay', ['record' => $kurum]))
            ->assertOk()
            ->assertSee('Evraklar')
            ->assertSee('Ticaret Sicili Gazetesi')
            ->assertSee('2026-KV-0001');
    }

    /** Ek talep belgesinin başlığı basılmalı (M2.3): iki belge ayırt edilebilsin. */
    public function test_ek_etiket_evrak_basliginda_gorunur(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Onaylandi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-KV-0002',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        $evrak = $this->evrak($basvuru, ekEtiket: 'Yayın sözleşmesi');

        $this->assertSame('Ticaret Sicili Gazetesi · Yayın sözleşmesi', $evrak->ekranBasligi());

        $this->actingAs($this->yetkili())
            ->get(KurumResource::getUrl('detay', ['record' => $kurum]))
            ->assertOk()
            ->assertSee('Yayın sözleşmesi');
    }

    /** Kişinin son başvurusunun evrakları kullanıcı detayında görünmeli. */
    public function test_kullanici_detayinda_evraklar_gorunur(): void
    {
        $kisi = User::create([
            'name' => 'Merve Kılıç', 'email' => 'merve@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuru_no' => '2026-BV-0007',
            'basvuran_eposta' => 'merve@ornek.test',
        ]);

        $this->evrak($basvuru);

        $this->actingAs($this->super())
            ->get(KullaniciResource::getUrl('detay', ['record' => $kisi]))
            ->assertOk()
            ->assertSee('Evraklar')
            ->assertSee('2026-BV-0007');
    }

    /**
     * 💀 İnceleme ekranı HİÇBİR testte render edilmiyordu.
     *
     * Bu boşluk gerçek bir hatayı canlıya taşıdı: `dosya-onizleme`
     * bileşenindeki bir yönerge kelimeye bitişik yazılınca derlenmiş Blade
     * ParseError veriyordu ve bileşeni kullanan HER ekran 500 dönüyordu.
     * Servis testleri bunu göremez -- sayfanın kendisi çizilmeli.
     *
     * Kurum teyidi bloğu da burada çiziliyor (M3).
     */
    public function test_inceleme_ekrani_evrak_ve_kurum_teyidiyle_cizilir(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Gonderildi,
            'kurum_id' => $kurum->id,
            'basvuru_no' => '2026-KV-0009',
            'basvuran_eposta' => 'iletisim@ornek.test',
            'gonderildi_at' => now()->subDays(3),
            'kurum_teyidi_gerekli' => true,
        ]);

        $this->evrak($basvuru, ekEtiket: 'Muvafakatname');

        $this->actingAs($this->yetkili())
            ->get(BasvuruResource::getUrl('inceleme', ['record' => $basvuru]))
            ->assertOk()
            ->assertSee('Kurum teyidi')
            ->assertSee('Bekleniyor')
            // Yönerge derlenmemiş olsaydı ham "@if" metni sayfaya sızardı.
            ->assertDontSee('@if')
            ->assertSee('Muvafakatname');
    }

    /**
     * 🔒 ÖNİZLEME SEÇİLENE KADAR YÜKLENMEZ -- M6.3 + KVKK erişim izi.
     *
     * 💀 Tüm önizlemeleri `x-show` ile çizmek, sayfayı AÇMAK demek her belgenin
     * adresini istemek demekti. `EvrakController` hassas evrakta
     * "evrak.goruntulendi" denetim kaydı yazdığı için bu, BAKILMAYAN kimlik
     * belgeleri için de erişim kaydı üretir ve KVKK izini kullanılamaz hâle
     * getirirdi. Aynı sebeple hassas evrakta küçük resim de yok.
     */
    public function test_hassas_evrakin_onizlemesi_ve_kucuk_resmi_pesin_yuklenmez(): void
    {
        $tur = EvrakTuru::create([
            'kod' => 'kimlik_gorseli',
            'ad' => 'Kimlik belgesi',
            'basvuru_turleri' => [BasvuruTuru::BasinMensubu->value],
            'zorunlu' => true,
            'izinli_formatlar' => ['jpg'],
            'maks_boyut_kb' => 8192,
            'hassas' => true,
            'sira' => 40,
            'aktif' => true,
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuru_no' => '2026-BV-0021',
            'basvuran_eposta' => 'muhabir@ornek.test',
            'basvuran_ad' => 'Muhabir',
            'gonderildi_at' => now(),
        ]);

        $evrak = Evrak::create([
            'basvuru_id' => $basvuru->id,
            'evrak_turu_id' => $tur->id,
            'disk' => 'evrak',
            'yol' => 'basvuru/x/kimlik.jpg',
            'orijinal_ad' => 'kimlik.jpg',
            'mime' => 'image/jpeg',
            'boyut' => 2048,
            'sifreli' => true,
        ]);

        $html = $this->actingAs($this->yetkili())
            ->get(BasvuruResource::getUrl('inceleme', ['record' => $basvuru]))
            ->assertOk()
            ->getContent();

        $adres = route('evrak.goster', $evrak);

        /*
         * Önizleme `<template x-if>` içinde. ⚠️ `<template>` GÖVDESİ HTML
         * kaynağında yine görünür -- tarayıcı onu yalnızca çalıştırmaz ve
         * içindeki adresi İSTEMEZ. Bu yüzden "adres metinde geçmesin" diye
         * bakılamaz; bakılacak şey adresin `template` dışında, peşin yüklenen
         * bir konumda olup olmadığı.
         */
        $this->assertStringContainsString('<template x-if=', $html);

        // Hassas evrakta küçük resim YOK. Küçük resim tek `loading="lazy"`
        // görselidir; imzası budur.
        $this->assertStringNotContainsString('src="'.$adres.'" alt="" loading="lazy"', $html);

        // Sunucu tarafında da "görüntülendi" kaydı DÜŞMEMELİ.
        $this->assertDatabaseMissing('denetim_kaydi', ['olay' => 'evrak.goruntulendi']);
    }

    /**
     * 💀 KURUMUN KENDİ HESABI ÇALIŞAN DEĞİL -- Cüneyt Bey revizyonu 05.09.2026.
     *
     * Kurum panelini kullanan yetkilinin hesabı da `kurum_id` taşıdığı için
     * çalışan listesinde görünüyordu; kulüp "bu kurumun N çalışanı var"
     * sanıyordu, oysa biri kurumun kendisiydi.
     *
     * 🔒 Ama ÇİFT ROLLÜ kişi listede KALMALI: gazetenin sahibi hem kurum
     * yetkilisi hem maça giden muhabir olabilir; onun kartı var.
     */
    public function test_kurum_detayinda_kurumun_kendi_hesabi_calisan_sayilmaz(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        // Yalnızca kurum rolü: listede GÖRÜNMEMELİ.
        $yetkiliHesap = User::create([
            'name' => 'Kurum Yetkilisi', 'email' => 'yetkili@ajans.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);
        $yetkiliHesap->assignRole(User::ROL_KURUM);

        // Çift rollü: hem yetkili hem basın mensubu -- GÖRÜNMELİ.
        $ciftRollu = User::create([
            'name' => 'Sahip Muhabir', 'email' => 'sahip@ajans.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);
        $ciftRollu->assignRole(User::ROL_KURUM);
        $ciftRollu->assignRole(User::ROL_BASIN);

        // Sıradan çalışan: GÖRÜNMELİ.
        $calisan = User::create([
            'name' => 'Muhabir Kişi', 'email' => 'muhabir@ajans.test',
            'password' => bcrypt('x'), 'aktif' => true, 'kurum_id' => $kurum->id,
        ]);
        $calisan->assignRole(User::ROL_BASIN);

        $govde = $this->actingAs($this->yetkili())
            ->get(KurumResource::getUrl('detay', ['record' => $kurum]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('yetkili@ajans.test', $govde);
        $this->assertStringContainsString('sahip@ajans.test', $govde);
        $this->assertStringContainsString('muhabir@ajans.test', $govde);
    }

    /**
     * 🔒 Değerlendirme sekmesi yetkiye bağlı. Yetkisi OLMAYAN bir hesapta
     * sekme hiç çizilmemeli -- puan ve not kulüp dışına çıkmaz.
     */
    public function test_degerlendirme_sekmesi_yetkisiz_kullaniciya_gorunmez(): void
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $yetkili = $this->yetkili();

        // Yetkili sekmeyi GÖRÜR.
        $this->actingAs($yetkili)
            ->get(KurumResource::getUrl('detay', ['record' => $kurum]))
            ->assertOk()
            ->assertSee('Değerlendirme');

        /*
         * Yetki ROLDEN geliyor; kullanıcıdan tek tek almak işe yaramaz.
         * Rolü de düşürmek panel erişimini komple keserdi (KurumPolicy::view
         * super/yetkili rolü arıyor) -- o zaman testin ölçtüğü şey sekme
         * görünürlüğü değil, giriş olurdu. Bu yüzden yalnızca ilgili yetki
         * rolden alınıyor. RefreshDatabase sayesinde etkisi bu teste özel.
         */
        Role::findByName(User::ROL_YETKILI)->revokePermissionTo('degerlendirme.yonet');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($yetkili->fresh())
            ->get(KurumResource::getUrl('detay', ['record' => $kurum]))
            ->assertOk()
            ->assertDontSee('Değerlendirme');
    }
}
