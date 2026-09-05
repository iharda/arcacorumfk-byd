<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Başvurum ne oldu?" ekranı.
 *
 * 💀 Reddedilen adayın hesabı hiç açılmıyor (hesap ONAY anında doğar), bu
 * yüzden giriş ekranı ona "E-posta veya şifre hatalı" diyordu -- doğru ama
 * cevapsız. Cevap artık burada.
 *
 * 🔒 Giriş ekranı hâlâ hesap var mı yok mu SÖYLEMİYOR; bu ekran iki bilgiyi
 * birden istediği için adres taramasına yaramıyor.
 */
class BasvuruDurumuSorgulamaTest extends TestCase
{
    use RefreshDatabase;

    private function reddedilmisBasvuru(): Basvuru
    {
        return Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Reddedildi,
            'basvuru_no' => '2026-BV-0031',
            'basvuran_ad' => 'Ali Veli',
            'basvuran_eposta' => 'ali@ornek.test',
            'gonderildi_at' => now()->subDays(10),
            'karar_at' => now()->subDays(3),
            'karar_gerekcesi' => 'Çalışma belgesi güncel değil.',
        ]);
    }

    public function test_dogru_bilgilerle_durum_gorunur(): void
    {
        $this->reddedilmisBasvuru();

        $this->post('/basvuru-durumu', [
            'basvuru_no' => '2026-BV-0031',
            'eposta' => 'ali@ornek.test',
        ])
            ->assertSuccessful()
            ->assertSee('2026-BV-0031')
            ->assertSee('Reddedildi')
            ->assertSee('Çalışma belgesi güncel değil.')
            // Çıkmaz sokak bırakmıyoruz: yeniden başvurabilir.
            ->assertSee('Yeniden başvur');
    }

    /** E-posta büyük/küçük harf farkı kişiyi çıkmaza sokmamalı. */
    public function test_eposta_buyuk_kucuk_harf_farki_engel_degil(): void
    {
        $this->reddedilmisBasvuru();

        $this->post('/basvuru-durumu', [
            'basvuru_no' => '2026-BV-0031',
            'eposta' => 'ALI@Ornek.TEST',
        ])->assertSuccessful()->assertSee('Reddedildi');
    }

    /**
     * 🔒 Numara doğru ama e-posta yanlışsa da AYNI cümle: aksi hâlde numarayı
     * bilen birine adres doğrulatılırdı.
     */
    public function test_yanlis_eposta_ile_ayirt_edilemez(): void
    {
        $this->reddedilmisBasvuru();

        $this->post('/basvuru-durumu', [
            'basvuru_no' => '2026-BV-0031',
            'eposta' => 'baskasi@ornek.test',
        ])
            ->assertSuccessful()
            ->assertSee('eşleşen bir kayıt bulunamadı')
            ->assertDontSee('Çalışma belgesi güncel değil.');
    }

    public function test_olmayan_numara_ayni_cumleyi_verir(): void
    {
        $this->reddedilmisBasvuru();

        $this->post('/basvuru-durumu', [
            'basvuru_no' => '2026-BV-9999',
            'eposta' => 'ali@ornek.test',
        ])->assertSuccessful()->assertSee('eşleşen bir kayıt bulunamadı');
    }

    /** Onaylanmış başvuruda hesap açılır; e-posta hesapta da aranmalı. */
    public function test_hesaba_bagli_basvuru_da_bulunur(): void
    {
        $kisi = User::create([
            'name' => 'Ayşe', 'email' => 'ayse@ornek.test',
            'password' => bcrypt('x'), 'aktif' => true,
        ]);

        Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'basvuru_no' => '2026-BV-0032',
            'kullanici_id' => $kisi->id,
            'basvuran_eposta' => null,
            'karar_at' => now()->subDay(),
        ]);

        $this->post('/basvuru-durumu', [
            'basvuru_no' => '2026-BV-0032',
            'eposta' => 'ayse@ornek.test',
        ])->assertSuccessful()->assertSee('Akredite edildi');
    }

    /** 🔑 Sayfanın keşfedilebilir olması şart: giriş ekranından bağlantı var. */
    public function test_giris_ekraninda_baglanti_var(): void
    {
        $this->get('/giris')
            ->assertSuccessful()
            ->assertSee('Başvurunuzun durumunu sorgulayın')
            ->assertSee('/basvuru-durumu', escape: false);
    }

    /** 🔒 Giriş ekranı hâlâ hesabın varlığını sızdırmıyor. */
    public function test_giris_mesaji_degismedi(): void
    {
        $this->reddedilmisBasvuru();

        $this->post('/giris', ['email' => 'ali@ornek.test', 'password' => 'yanlis'])
            ->assertSessionHasErrors(['email' => 'E-posta veya şifre hatalı.']);
    }
}
