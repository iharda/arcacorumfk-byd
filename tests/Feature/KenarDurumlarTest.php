<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Http\Requests\KurumBasvuruIstegi;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Notifications\KurumTeyidiIstendi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\DegerlendirmeAkisi;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Sorulmayan ama patlayacak kenar durumlar -- Tutarsızlık incelemesi M9.
 *
 * Hepsinin ortak yanı: sistem teknik olarak "doğru" davranıyor ama sonuç
 * kullanıcı için çıkmaz sokak oluyor. Test edilen şey kod yolu değil, o
 * çıkmaz sokağın kapanmış olması.
 */
class KenarDurumlarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
        Notification::fake();
    }

    private function akis(): BasvuruAkisi
    {
        return app(BasvuruAkisi::class);
    }

    /** @return array{Kurum, User} kurum ve (aktif) yetkilisi */
    private function kurumVeYetkili(bool $yetkiliAktif = true): array
    {
        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
            'teyit_istensin' => true,
        ]);

        $yetkili = User::create([
            'name' => 'Kurum Yetkilisi', 'email' => 'yetkili@ajans.test',
            'password' => bcrypt('x'), 'aktif' => $yetkiliAktif, 'kurum_id' => $kurum->id,
        ]);
        $yetkili->assignRole(User::ROL_KURUM);

        return [$kurum, $yetkili];
    }

    private function basinBasvurusu(Kurum $kurum): Basvuru
    {
        return Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Taslak,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Muhabir',
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);
    }

    /* ─────────── M9 №5 · Ulaşılamayan kurum = kayıp başvuru ─────────── */

    /** Aktif yetkili varken teyit normal istenir. */
    public function test_aktif_yetkili_varken_teyit_istenir(): void
    {
        [$kurum] = $this->kurumVeYetkili();

        $basvuru = $this->basinBasvurusu($kurum);
        $this->akis()->gonder($basvuru);

        $this->assertTrue($basvuru->refresh()->kurum_teyidi_gerekli);
        Notification::assertSentTimes(KurumTeyidiIstendi::class, 1);
    }

    /**
     * 💀 ASIL HATA: kurumun tek yetkilisi pasifse hiçbir bildirim gitmiyordu ve
     * başvuru `scopeKuyrukta()` dışında kaldığı için kulübün listesinde HİÇ
     * görünmüyordu. Kimse beklediğini bilmiyor, kimse fark etmiyordu.
     */
    public function test_ulasilabilir_yetkili_yoksa_teyit_istenmez_ve_basvuru_kuyruga_duser(): void
    {
        [$kurum] = $this->kurumVeYetkili(yetkiliAktif: false);

        $basvuru = $this->basinBasvurusu($kurum);
        $this->akis()->gonder($basvuru);

        $basvuru->refresh();

        $this->assertFalse($basvuru->kurum_teyidi_gerekli, 'Cevaplayacak kimse yokken teyit istenmemeli.');
        Notification::assertNothingSentTo($kurum->calisanlar()->get());

        // Asıl kanıt: başvuru kulübün kuyruğunda GÖRÜNÜYOR.
        $this->assertTrue(
            Basvuru::query()->kuyrukta()->whereKey($basvuru->id)->exists(),
            'Teyit beklemeyen başvuru kulüp kuyruğunda görünmeli.',
        );

        // "Neden teyit sorulmadı" sorusunun cevabı kayıtta dursun.
        $this->assertDatabaseHas('denetim_kaydi', ['olay' => 'basvuru.kurum_teyidi_atlandi']);
    }

    /** Kurumdan ayrılmış yetkili de teyit veremez. */
    public function test_ayrilmis_yetkili_teyit_hedefi_sayilmaz(): void
    {
        [$kurum, $yetkili] = $this->kurumVeYetkili();
        $yetkili->forceFill(['ayrildi_at' => now()])->save();

        $basvuru = $this->basinBasvurusu($kurum);
        $this->akis()->gonder($basvuru);

        $this->assertFalse($basvuru->refresh()->kurum_teyidi_gerekli);
    }

    /* ─────────── M9 №8 · İki yetkili aynı anda ─────────── */

    /**
     * 💀 İkinci yetkili karar verince "Geçersiz durum geçişi: onaylandi →
     * onaylandi" gibi ham bir hata görüyordu. Ne olduğunu anlatmıyor, ne
     * yapacağını hiç söylemiyordu.
     */
    public function test_gecersiz_gecis_insan_okur_mesaj_verir(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);

        try {
            $basvuru->durumaGec(BasvuruDurumu::Onaylandi);
            $this->fail('Geçersiz geçiş hata fırlatmalıydı.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('Geçersiz durum geçişi', $e->getMessage());
            $this->assertStringContainsString('Başka bir yetkili', $e->getMessage());
            // Durum adı kullanıcının listede gördüğü etiketle aynı olmalı.
            $this->assertStringContainsString(BasvuruDurumu::Onaylandi->etiket(), $e->getMessage());
        }
    }

    /* ─────────── M9 №9 · E-posta değişince değerlendirme kopuyordu ─────────── */

    /** 💀 Puan eski adreste kalıyor, kişi "değerlendirilmemiş" görünüyordu. */
    public function test_degerlendirme_yeni_epostaya_tasinir(): void
    {
        $this->yetkiliOlarakGir();

        app(DegerlendirmeAkisi::class)->kisiyeYaz('eski@ornek.test', 'Merve Kılıç', 5, 'Sorunsuz');

        $tasinan = app(DegerlendirmeAkisi::class)->epostayiTasi('eski@ornek.test', 'YENI@ornek.test');

        $this->assertNotNull($tasinan);
        $this->assertSame('yeni@ornek.test', $tasinan->eposta, 'Anahtar küçük harfe indirgenmeli.');
        $this->assertNotNull(app(DegerlendirmeAkisi::class)->kisiIcin('yeni@ornek.test'));
        $this->assertNull(app(DegerlendirmeAkisi::class)->kisiIcin('eski@ornek.test'));
        $this->assertDatabaseHas('denetim_kaydi', ['olay' => 'degerlendirme.eposta_tasindi']);
    }

    /**
     * 🔒 Yeni adreste ZATEN değerlendirme varsa taşımayız: iki geçmişi
     * birleştirmek hangi notun kalacağına karar vermek demek ve o kararı
     * sessizce vermemeliyiz.
     */
    public function test_hedef_adreste_degerlendirme_varsa_tasinmaz(): void
    {
        $this->yetkiliOlarakGir();

        $akis = app(DegerlendirmeAkisi::class);
        $akis->kisiyeYaz('eski@ornek.test', 'Eski', 5, null);
        $akis->kisiyeYaz('yeni@ornek.test', 'Yeni', 1, null);

        $this->assertNull($akis->epostayiTasi('eski@ornek.test', 'yeni@ornek.test'));

        // İkisi de yerinde durmalı.
        $this->assertSame(5, $akis->kisiIcin('eski@ornek.test')->puan->value);
        $this->assertSame(1, $akis->kisiIcin('yeni@ornek.test')->puan->value);
    }

    private function yetkiliOlarakGir(): User
    {
        $u = User::create([
            'name' => 'Yetkili', 'email' => 'kulup@kulup.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);
        $u->assignRole(User::ROL_YETKILI);
        Auth::login($u);

        return $u;
    }

    /* ─────────── M9 №4 · Yetkilisi değişen kurum ─────────── */

    /**
     * Çıkmaz sokak mesajı: eski metin "yetkilisiyle görüşün" diyordu ama o kişi
     * ayrılmış olabilir. Yeni metin kulübü işaret etmeli.
     */
    public function test_vergi_no_mesaji_kulube_yonlendirir(): void
    {
        $mesaj = (new KurumBasvuruIstegi)->messages()['vergi_no.unique'];

        $this->assertStringContainsString('kulüple iletişime geçin', $mesaj);
        $this->assertStringNotContainsString('yetkilisiyle görüşün', $mesaj);
    }

    /** Ayar kapalıyken teyit hiç istenmemeli (mevcut davranış korunuyor). */
    public function test_ayar_kapaliyken_teyit_istenmez(): void
    {
        Ayar::yaz('kurum_teyidi_istensin', false);

        $kurum = Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => 'akredite',
        ]);

        $basvuru = $this->basinBasvurusu($kurum);
        $this->akis()->gonder($basvuru);

        $this->assertFalse($basvuru->refresh()->kurum_teyidi_gerekli);
        $this->assertDatabaseMissing('denetim_kaydi', ['olay' => 'basvuru.kurum_teyidi_atlandi']);
    }
}
