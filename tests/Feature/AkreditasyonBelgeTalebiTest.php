<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\DuzeltmeTuru;
use App\Filament\Yonetim\Ortak\BelgeTalebiEylemi;
use App\Filament\Yonetim\Ortak\TalepAlanlari;
use App\Filament\Yonetim\Resources\Akreditasyonlar\Pages\AkreditasyonDetay;
use App\Filament\Yonetim\Widgets\DikkatGerektirenler;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Models\User;
use App\Notifications\BelgeTalebi as BelgeTalebiBildirimi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use Database\Seeders\AyarSeeder;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * AKREDİTE KİŞİDEN BELGE TALEBİ -- Cüneyt Bey revizyonu (05.09.2026).
 *
 * 💀 Yusuf Demir aktif akreditasyonu olan bir muhabir. Ondan bir belge istemek
 * isteyen yetkili akreditasyon detayında "Ek evrak talep et"e basıyor, inceleme
 * ekranına düşüyor, orada "Belge iste" pasif çıkıyor ve tooltip "önce
 * Akreditasyonu geri al" diyordu. O adım kartı GERİ ALINAMAZ biçimde iptal
 * ediyor, rolü düşürüyor ve bütün onay turunu baştan başlatıyor. Bir eksik
 * fotoğraf için kart yakılıyordu.
 *
 * Bu testin koruduğu sözleşme: talep açılır, KART DOKUNULMAZ, başvurunun
 * durumu DEĞİŞMEZ, kişi kendi panelinden görür ve yükler.
 */
class AkreditasyonBelgeTalebiTest extends TestCase
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
            'kod' => 'kimlik_gorseli',
            'ad' => 'Kimlik / ehliyet / pasaport',
            'basvuru_turleri' => [BasvuruTuru::BasinMensubu->value],
            'zorunlu' => true,
            'izinli_formatlar' => ['pdf', 'jpg'],
            'maks_boyut_kb' => 4096,
            'hassas' => true,
            'sira' => 1,
            'aktif' => true,
        ]);
    }

    /** Yusuf Demir: hesabı olan, kartı AKTİF, başvurusu ONAYLANMIŞ muhabir. */
    private function akredite(): Akreditasyon
    {
        // Panel `emailVerification()` istiyor: doğrulanmamış hesap /panel'de
        // 302 yer; onay anında açılan gerçek hesap da doğrulanmış doğar.
        $kisi = User::create([
            'name' => 'Yusuf Demir', 'email' => 'yusuf@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
            'email_verified_at' => now(),
        ]);
        $kisi->assignRole(User::ROL_BASIN);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kisi->id,
            'basvuru_no' => '2026-BV-0101',
            'basvuran_ad' => 'Yusuf Demir',
            'basvuran_eposta' => 'yusuf@ornek.test',
            'karar_gerekcesi' => 'Belgeleri tamam, onaylandı.',
        ]);

        return Akreditasyon::create([
            'kart_no' => '2026-BM-0101',
            'yil' => 2026, 'tur_kodu' => 'BM', 'sira' => 101,
            'kullanici_id' => $kisi->id,
            'basvuru_id' => $basvuru->id,
            'durum' => AkreditasyonDurumu::Aktif,
        ]);
    }

    private function talepEt(Basvuru $basvuru, int $gun = 7): BasvuruDuzeltmesi
    {
        return app(BasvuruAkisi::class)->belgeTalepEt(
            $basvuru,
            ['evrak:kimlik_gorseli' => 'Kimlik belgenizin süresi dolmuş, güncelini gönderin.'],
            'Sezon başı güncellemesi.',
            [],
            $gun,
        );
    }

    /* ─────────────────────────── SERVİS ─────────────────────────── */

    /** 💀 Sözleşmenin kalbi: kart ve durum DOKUNULMAZ. */
    public function test_talep_karti_ve_basvuru_durumunu_degistirmez(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);

        $this->assertSame(BasvuruDurumu::Onaylandi, $akreditasyon->basvuru->fresh()->durum);
        $this->assertSame(AkreditasyonDurumu::Aktif, $akreditasyon->fresh()->durum);
        $this->assertSame(DuzeltmeTuru::BelgeTalebi, $talep->tur);
        $this->assertSame(
            now()->copy()->addDays(7)->toDateString(),
            $talep->son_tarih->toDateString(),
        );
    }

    /** Varsayılan süre 7 gün ve kişiye bu tarihle bildirilir. */
    public function test_yedi_gunluk_sure_bildirimde_yazar(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);

        // Hesabı olan başvuranda hedef `bildirimHedefi()` ile kullanıcıdır.
        Notification::assertSentTo(
            $akreditasyon->kullanici,
            BelgeTalebiBildirimi::class,
            fn (BelgeTalebiBildirimi $b) => $b->talep->is($talep),
        );

        $posta = (new BelgeTalebiBildirimi($akreditasyon->basvuru, $talep, 'deneme-token'))
            ->toMail($akreditasyon->kullanici)
            ->render()
            ->toHtml();

        // 🔑 Metnin en önemli iki cümlesi: kart duruyor + son tarih.
        $this->assertStringContainsString('geçerliliğini koruyor', $posta);
        $this->assertStringContainsString(
            $talep->son_tarih->timezone('Europe/Istanbul')->format('d.m.Y'),
            $posta,
        );
    }

    /**
     * 🪤 `karar_gerekcesi` ONAY gerekçesidir; talebin mesajı oraya yazılırsa
     * kararın kendi kaydı silinir. Mesaj turun üstünde durmalı.
     */
    public function test_onay_gerekcesi_ezilmez(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);

        $this->assertSame('Belgeleri tamam, onaylandı.', $akreditasyon->basvuru->fresh()->karar_gerekcesi);
        $this->assertSame('Sezon başı güncellemesi.', $talep->talep_gerekcesi);
    }

    /** Karara bağlanmamış başvuruda bu kapı açılmaz -- orası inceleme ekranının işi. */
    public function test_onaylanmamis_basvuruda_talep_acilmaz(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();
        $akreditasyon->basvuru->forceFill(['durum' => BasvuruDurumu::Incelemede])->save();

        $this->expectException(RuntimeException::class);
        $this->talepEt($akreditasyon->basvuru->fresh());
    }

    /**
     * 🪤 TEK AÇIK TUR: `duzeltme_notlari` tek alan, ikinci tur birincinin
     * istediklerini sessizce silerdi.
     */
    public function test_acik_talep_varken_ikincisi_acilmaz(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $this->talepEt($akreditasyon->basvuru);

        $this->expectException(RuntimeException::class);
        $this->talepEt($akreditasyon->basvuru->fresh());
    }

    /** Tur numarası tek seri: düzeltme 01'den sonra belge talebi 02 olur. */
    public function test_tur_numarasi_tek_seri(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        BasvuruDuzeltmesi::create([
            'basvuru_id' => $akreditasyon->basvuru->id,
            'sira' => 1,
            'talep_notlari' => ['veri:adres' => 'Eski tur.'],
            'talep_at' => now()->subMonth(),
            'yanit_at' => now()->subMonth(),
        ]);

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);

        $this->assertSame(2, $talep->sira);
        $this->assertSame('Belge talebi 02', $talep->baslik());
    }

    /* ─────────────────────────── EKRANLAR ─────────────────────────── */

    /** Yetkilinin ekranı: eylem gerçekten burada, bağlantı değil. */
    public function test_akreditasyon_detayinda_belge_iste_eylemi_var(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        $this->get("/yonetim/akreditasyonlar/{$akreditasyon->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('Belge iste')
            ->assertDontSee('Ek evrak talep et');

        $this->assertNull(BelgeTalebiEylemi::akreditasyonEngeli($akreditasyon->fresh()));
    }

    /** Açık talep varsa uyarı bandı ve süre künyenin altında görünür. */
    public function test_detayda_bant_ve_sure_gorunur(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $this->talepEt($akreditasyon->basvuru);

        $this->get("/yonetim/akreditasyonlar/{$akreditasyon->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('Belge bekleniyor')
            ->assertSee('Kart aktif kalmaya devam ediyor', false)
            ->assertSee('Belge talebi 01');

        // İkinci talep engellenir ve sebebi ekranda yazılabilir olmalı.
        $this->assertSame(
            'Bu başvuruda yanıtlanmamış bir talep zaten var; önce o kapansın.',
            BelgeTalebiEylemi::akreditasyonEngeli($akreditasyon->fresh()),
        );
    }

    /** 🔒 Kartı iptal edilmiş akreditasyonda talep açılamaz. */
    public function test_iptal_edilmis_kartta_eylem_pasif(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();
        $akreditasyon->forceFill(['durum' => AkreditasyonDurumu::Iptal])->save();

        $this->assertNotNull(BelgeTalebiEylemi::akreditasyonEngeli($akreditasyon->fresh()));
    }

    /**
     * 💀 Kişinin KENDİ PANELİNDE görmesi şart: e-posta silinmişse yükleyecek
     * başka yeri yok. Eski ölçüt `durum === eksik_evrak` idi ve belge
     * talebinde durum `onaylandi` kaldığı için sayfa hiçbir şey göstermiyordu.
     */
    public function test_uye_panelinde_talep_gorunur(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $this->talepEt($akreditasyon->basvuru);

        $this->actingAs($akreditasyon->kullanici);

        $this->get('/panel/basvurum')
            ->assertSuccessful()
            ->assertSee('Belge bekleniyor')
            ->assertSee('Basın kartınız geçerliliğini koruyor', false)
            ->assertSee('Kimlik / ehliyet / pasaport');

        // Kart sahibi panele kartına bakmaya gelir; şerit orada da olmalı.
        $this->get('/panel/kartim')
            ->assertSuccessful()
            ->assertSee('Belge bekleniyor');
    }

    /* ──────────────────── MODAL: KALEM DOĞRULAMASI ──────────────────── */

    /**
     * 💀 LİSTEDE OLMAYAN EK TALEP TEK BAŞINA GÖNDERİLEMİYORDU (İbrahim Bey,
     * 05.09.2026). Üstteki liste `minItems(1)` idi; yetkili başlığı ve
     * açıklamayı ek talep bölümüne yazıp gönderince "İstenen belgeler en az
     * bir öğe içermelidir" hatası alıyordu. Oysa servis bu gönderimi kabul
     * ediyor -- kilit yalnızca formdaydı ve çıkış yolunu söylemiyordu.
     */
    public function test_yalnizca_ek_talep_gonderilebilir(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();

        Livewire::test(AkreditasyonDetay::class, ['record' => $akreditasyon->ulid])
            ->callAction('belgeIste', [
                // Üstteki liste bilerek BOŞ: varsayılan satıra dokunulmadı.
                'notlar' => [['alan' => null, 'aciklama' => null]],
                'ek_talepler' => [[
                    'etiket' => 'Yayın sözleşmesi',
                    'tip' => 'dosya',
                    'aciklama' => 'Kurumla imzalı sözleşmenizi gönderin.',
                ]],
                'sure_gun' => 7,
                'mesaj' => null,
            ])
            ->assertHasNoActionErrors();

        $talep = $akreditasyon->basvuru->fresh()->acikBelgeTalebi();

        $this->assertNotNull($talep, 'Yalnızca ek talep içeren gönderim reddedildi.');
        $this->assertSame(['ek:1'], array_keys($talep->talep_notlari));
        $this->assertSame('Yayın sözleşmesi', $talep->ek_talepler[0]['etiket']);
        $this->assertSame(BasvuruDurumu::Onaylandi, $akreditasyon->basvuru->fresh()->durum);
    }

    /** Listedeki kalem tek başına da gönderilebilir; ek talep zorunlu değil. */
    public function test_yalnizca_listedeki_kalem_gonderilebilir(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();

        Livewire::test(AkreditasyonDetay::class, ['record' => $akreditasyon->ulid])
            ->callAction('belgeIste', [
                'notlar' => [['alan' => 'evrak:kimlik_gorseli', 'aciklama' => 'Süresi dolmuş.']],
                'ek_talepler' => [],
                'sure_gun' => 7,
            ])
            ->assertHasNoActionErrors();

        $talep = $akreditasyon->basvuru->fresh()->acikBelgeTalebi();

        $this->assertNotNull($talep);
        $this->assertSame(['evrak:kimlik_gorseli'], array_keys($talep->talep_notlari));
    }

    /** İki liste de boşsa talep açılmaz; sebebi okunur bir cümle olmalı. */
    public function test_iki_liste_de_bossa_talep_acilmaz(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        $this->assertNotNull(TalepAlanlari::kalemHatasi([['alan' => null, 'aciklama' => null]], []));

        Notification::fake();

        Livewire::test(AkreditasyonDetay::class, ['record' => $akreditasyon->ulid])
            ->callAction('belgeIste', [
                'notlar' => [['alan' => null, 'aciklama' => null]],
                'ek_talepler' => [],
                'sure_gun' => 7,
            ]);

        $this->assertNull($akreditasyon->basvuru->fresh()->acikBelgeTalebi());
    }

    /**
     * 🪤 YARIM SATIR SESSİZCE ATILMAZ: açıklama yazılıp kalem seçilmemişse
     * yetkilinin emeği kaybolur. Hata satırın hangisi olduğunu söylemeli.
     */
    public function test_yarim_satir_yakalanir(): void
    {
        $this->assertStringContainsString(
            'kalem seçilmemiş',
            TalepAlanlari::kalemHatasi([['alan' => null, 'aciklama' => 'Bir şey yazdım']], []) ?? '',
        );

        $this->assertStringContainsString(
            'açıklamasını da yazın',
            TalepAlanlari::kalemHatasi([['alan' => 'evrak:kimlik_gorseli', 'aciklama' => null]], []) ?? '',
        );

        // Dokunulmamış boş satır + dolu ek talep: hata YOK.
        $this->assertNull(TalepAlanlari::kalemHatasi(
            [['alan' => null, 'aciklama' => null]],
            [['etiket' => 'Sözleşme', 'tip' => 'dosya', 'aciklama' => 'Gönderin']],
        ));
    }

    /**
     * 💀 EK TALEP EKRANDA İKİ KEZ ÇİZİLİYORDU (İbrahim Bey, 05.09.2026).
     *
     * `talep_notlari` ek talepleri de taşır -- taşımak ZORUNDA, düzeltme formu
     * hangi kutuları açacağını oradan okuyor. Ama `maddeler()` bir de
     * `ek_talepler`i dolaşıyordu: aynı kalem "Belge talebi 01" altında iki
     * satır görünüyordu. Hata yeni ekranda fark edildi ama eskiydi; başvuru
     * inceleme ekranı ve panelsiz düzeltme geçmişi de aynı kopyayı basıyordu.
     */
    public function test_ek_talep_tek_satir_cizilir(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();

        $talep = app(BasvuruAkisi::class)->belgeTalepEt(
            $akreditasyon->basvuru,
            [],
            null,
            [[
                'anahtar' => 'ek:1',
                'etiket' => 'Yayın sözleşmesi',
                'tip' => 'dosya',
                'aciklama' => 'İmzalı nüshayı gönderin.',
            ]],
        );

        $maddeler = $talep->maddeler();

        $this->assertCount(1, $maddeler, 'Ek talep iki kez çizildi.');
        $this->assertSame('Yayın sözleşmesi', $maddeler[0]['etiket']);
        $this->assertTrue($maddeler[0]['ek']);

        // Ekranda da tek satır: rozet metni bir kez geçmeli.
        $icerik = $this->get("/yonetim/akreditasyonlar/{$akreditasyon->ulid}/detay")
            ->assertSuccessful()
            ->getContent();

        $this->assertSame(1, substr_count($icerik, 'Yayın sözleşmesi'), 'Ekranda mükerrer satır var.');
    }

    /** Listedeki kalem ile ek talep birlikte gönderilirse ikisi de bir kez çizilir. */
    public function test_karisik_talepte_her_kalem_bir_kez(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();

        $talep = app(BasvuruAkisi::class)->belgeTalepEt(
            $akreditasyon->basvuru,
            ['evrak:kimlik_gorseli' => 'Süresi dolmuş.'],
            null,
            [[
                'anahtar' => 'ek:1',
                'etiket' => 'Yayın sözleşmesi',
                'tip' => 'dosya',
                'aciklama' => 'İmzalı nüshayı gönderin.',
            ]],
        );

        $etiketler = array_column($talep->maddeler(), 'etiket');

        $this->assertSame(['Kimlik / ehliyet / pasaport', 'Yayın sözleşmesi'], $etiketler);
    }

    /* ────────────────────── YÜKLEME (UÇTAN UCA) ────────────────────── */

    /**
     * 💀 ASIL SÖZLEŞME BURADA. Belge geldiğinde:
     *   - başvuru `yeniden_inceleme`ye GİRMEZ (o `gonder()`in işi, burada
     *     çağrılmıyor), `onaylandi` kalır
     *   - kart AKTİF kalır, yeni kart üretilmez
     *   - evrak dosyaya girer, tur kapanır, şerit söner
     */
    public function test_belge_yuklenince_kart_ve_durum_yerinde_kalir(): void
    {
        Storage::fake(config('bys.evrak_disk'));

        $this->actingAs($this->yetkili());
        $tur = $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);

        $token = app(BasvuruBiletiAkisi::class)->uret($akreditasyon->basvuru->fresh(), 'belge_talebi');

        // 🪤 Başvuru `onaylandi` olduğu hâlde bağlantı AÇILMALI: eski kapı
        // `durum === eksik_evrak` sorup akredite kişiyi 410 ile kapatıyordu.
        $this->get(route('basvuru.duzelt', ['token' => $token]))
            ->assertOk()
            ->assertSee('İstenen belgeyi gönderin')
            ->assertSee('Belge talebi 01')
            ->assertSee('Basın kartınız geçerliliğini koruyor', false);

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'evraklar' => [$tur->id => UploadedFile::fake()->image('kimlik.jpg', 400, 300)],
            'aciklama' => 'Güncel kimliğimi yükledim.',
        ])->assertRedirect(route('basvuru.gonderildi'));

        $basvuru = $akreditasyon->basvuru->fresh();

        $this->assertSame(BasvuruDurumu::Onaylandi, $basvuru->durum, 'Başvuru yeniden incelemeye girdi.');
        $this->assertSame(AkreditasyonDurumu::Aktif, $akreditasyon->fresh()->durum, 'Kart durumu değişti.');
        $this->assertNull($basvuru->duzeltme_notlari, 'Açık tur işareti silinmedi; şerit sönmez.');
        $this->assertNotNull($talep->fresh()->yanit_at, 'Tur kapanmadı.');
        $this->assertNull($basvuru->acikBelgeTalebi());

        $this->assertTrue(
            $basvuru->evraklar()->where('evrak_turu_id', $tur->id)->exists(),
            'Yüklenen belge başvuru dosyasına girmedi.',
        );
    }

    /**
     * Dönüş sayfası ÜÇÜNCÜ hâli anlatmalı: "başvurunuz yeniden incelemeye
     * alındı" cümlesi akredite kişi için düpedüz yanlış.
     */
    public function test_donus_sayfasi_yeniden_inceleme_demez(): void
    {
        Storage::fake(config('bys.evrak_disk'));

        $this->actingAs($this->yetkili());
        $tur = $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $this->talepEt($akreditasyon->basvuru);

        $token = app(BasvuruBiletiAkisi::class)->uret($akreditasyon->basvuru->fresh(), 'belge_talebi');

        $this->post(route('basvuru.duzelt.kaydet', ['token' => $token]), [
            'evraklar' => [$tur->id => UploadedFile::fake()->image('kimlik.jpg', 400, 300)],
        ]);

        $this->get(route('basvuru.gonderildi'))
            ->assertOk()
            ->assertSee('Belgeniz alındı')
            ->assertDontSee('yeniden incelemeye alındı');
    }

    /**
     * ⚠️ SÜRENİN YAPTIRIMI YOK. Süre geçince kart askıya alınmaz, talep
     * kapanmaz; kayıt yalnızca yetkilinin önüne düşer.
     */
    public function test_sure_gecince_kart_dokunulmaz_kayit_panoya_duser(): void
    {
        $this->actingAs($this->yetkili());
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        Notification::fake();
        $talep = $this->talepEt($akreditasyon->basvuru);
        $talep->forceFill(['son_tarih' => now()->subDays(3)->startOfDay()])->save();

        $this->assertTrue($talep->fresh()->suresiGectiMi());
        $this->assertSame(AkreditasyonDurumu::Aktif, $akreditasyon->fresh()->durum);
        $this->assertNull($talep->fresh()->yanit_at);

        $sebepler = DikkatGerektirenler::satirlar()->pluck('sebep');
        $this->assertTrue($sebepler->contains('Belge talebinin süresi geçti'));

        $this->get("/yonetim/akreditasyonlar/{$akreditasyon->ulid}/detay")
            ->assertSuccessful()
            ->assertSee('kararı siz verin', false);
    }

    /** Talep yokken üye panelinde hiçbir şerit çizilmez. */
    public function test_talep_yoksa_serit_yok(): void
    {
        $this->evrakTuru();
        $akreditasyon = $this->akredite();

        $this->actingAs($akreditasyon->kullanici);

        $this->get('/panel/kartim')->assertSuccessful()->assertDontSee('Belge bekleniyor');
        $this->get('/panel/basvurum')->assertSuccessful()->assertDontSee('Belge bekleniyor');
    }
}
