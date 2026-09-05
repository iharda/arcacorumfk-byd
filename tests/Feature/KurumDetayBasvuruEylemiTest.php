<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use Database\Seeders\RolYetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kurum detayındaki "başvuruya git" düğmesi.
 *
 * 🔑 Videodaki şikâyet: "Kurumlar sadece okuma ekranı, ben burada hiçbir işlem
 * yapamıyorum." Eylemler İnceleme ekranında yaşamaya devam ediyor; kurum
 * detayı oraya GÖTÜRÜYOR ve düğme başvurunun bugünkü durumunu söylüyor.
 */
class KurumDetayBasvuruEylemiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolYetkiSeeder::class);
    }

    private function yetkili(): User
    {
        $k = User::create([
            'name' => 'Süper Kullanıcı',
            'email' => 'super@ornek.test',
            'password' => bcrypt('x'),
            'aktif' => true,
        ]);
        $k->assignRole(User::ROL_SUPER);
        // Yönetim panelinde 2FA zorunlu; gizli anahtar yoksa sayfa hiç açılmaz.
        $k->forceFill(['iki_adimli_gizli' => 'JBSWY3DPEHPK3PXP'])->save();

        return $k->fresh();
    }

    private function kurum(string $durum = 'beklemede'): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => 'Çorum Haber Ajansı',
            'akreditasyon_durumu' => $durum,
        ]);
    }

    private function basvuru(Kurum $kurum, BasvuruDurumu $durum, BasvuruTuru $tur = BasvuruTuru::Kurum): Basvuru
    {
        return Basvuru::create([
            'tur' => $tur,
            'durum' => $durum,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => 'Kurum Yetkilisi',
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);
    }

    private function detay(Kurum $kurum): TestResponse
    {
        return $this->actingAs($this->yetkili())
            ->get("/yonetim/kurumlar/{$kurum->ulid}/detay")
            ->assertSuccessful();
    }

    /**
     * Yalnızca BAŞLIK ŞERİDİNDEKİ eylem düğmelerinin adresleri.
     *
     * 🪤 Sayfanın ham gövdesinde başvuru adresi aramak işe yaramaz: "Başvuru
     * geçmişi" sekmesi kurumun BÜTÜN başvurularını zaten bağlantı olarak
     * listeliyor. Düğmenin hangi başvuruyu hedeflediğini ölçmek için eylem
     * bağlantılarını (`fi-ac-btn-action`) ayırmak şart.
     *
     * @return array<int, string>
     */
    private function eylemAdresleri(TestResponse $cevap): array
    {
        preg_match_all('/<a\b[^>]*\bfi-ac-btn-action\b[^>]*>/s', $cevap->getContent(), $etiketler);

        return collect($etiketler[0])
            ->map(fn (string $etiket) => preg_match('/href="([^"]*)"/', $etiket, $h) ? $h[1] : null)
            ->filter()
            ->values()
            ->all();
    }

    private function assertDugmeHedefi(TestResponse $cevap, Basvuru $beklenen): void
    {
        $this->assertContains(
            "/yonetim/basvurular/{$beklenen->ulid}/inceleme",
            array_map(fn (string $a) => parse_url($a, PHP_URL_PATH), $this->eylemAdresleri($cevap)),
        );
    }

    /** Etiket başvurunun durumundan geliyor -- düğme "burada iş var mı"yı söyler. */
    public static function durumEtiketleri(): array
    {
        return [
            'inceleme bekliyor' => [BasvuruDurumu::Gonderildi, 'Başvuruyu incele'],
            'yeniden inceleme' => [BasvuruDurumu::YenidenInceleme, 'Başvuruyu incele'],
            'inceleniyor' => [BasvuruDurumu::Incelemede, 'İncelemeye devam et'],
            'belge bekleniyor' => [BasvuruDurumu::EksikEvrak, 'Belge bekleyen başvuruya git'],
            'taslak' => [BasvuruDurumu::Taslak, 'Taslak başvuruyu gör'],
            'onaylandi' => [BasvuruDurumu::Onaylandi, 'Başvuru detayına git'],
            'reddedildi' => [BasvuruDurumu::Reddedildi, 'Başvuru detayına git'],
            'iptal edildi' => [BasvuruDurumu::IptalEdildi, 'Başvuru detayına git'],
        ];
    }

    #[DataProvider('durumEtiketleri')]
    public function test_dugme_etiketi_basvuru_durumuna_gore_degisir(
        BasvuruDurumu $durum,
        string $beklenen,
    ): void {
        $kurum = $this->kurum();
        $basvuru = $this->basvuru($kurum, $durum);

        $cevap = $this->detay($kurum)->assertSee($beklenen);

        $this->assertDugmeHedefi($cevap, $basvuru);
    }

    /**
     * 🪤 İŞ OLAN ÖNCE. Kurumun eski bir kararı ve yeni bir başvurusu varsa
     * düğme karara değil, bekleyen başvuruya götürmeli -- yetkilinin gideceği
     * yer orası.
     */
    public function test_kuyruktaki_basvuru_karara_baglanmisin_onune_gecer(): void
    {
        $kurum = $this->kurum('iptal');
        $this->basvuru($kurum, BasvuruDurumu::Reddedildi);
        $bekleyen = $this->basvuru($kurum, BasvuruDurumu::Gonderildi);

        $cevap = $this->detay($kurum)->assertSee('Başvuruyu incele');

        $this->assertDugmeHedefi($cevap, $bekleyen);
    }

    /**
     * 🪤 `basvurular()` ilişkisi `kurum_id` üzerinden çalışıyor, yani
     * ÇALIŞANLARIN bireysel başvuruları da geliyor. Onların kararı kurumun
     * akreditasyonunu değiştirmez; düğme oraya götürürse yetkili yanlış
     * başvuruyu inceler.
     */
    public function test_calisanin_bireysel_basvurusu_hedef_secilmez(): void
    {
        $kurum = $this->kurum();
        $kurumsal = $this->basvuru($kurum, BasvuruDurumu::Onaylandi);
        $bireysel = $this->basvuru($kurum, BasvuruDurumu::Gonderildi, BasvuruTuru::BasinMensubu);

        $cevap = $this->detay($kurum)->assertSee('Başvuru detayına git');

        $this->assertDugmeHedefi($cevap, $kurumsal);

        // Bireysel başvurunun adresi sayfada VAR (başvuru geçmişi sekmesi),
        // ama düğmenin hedefi O DEĞİL. Ölçtüğümüz şey tam olarak bu fark.
        $this->assertNotContains(
            "/yonetim/basvurular/{$bireysel->ulid}/inceleme",
            array_map(fn (string $a) => parse_url($a, PHP_URL_PATH), $this->eylemAdresleri($cevap)),
        );
    }

    /** Kurumsal başvurusu olmayan kurumda düğme hiç çizilmez. */
    public function test_kurumsal_basvuru_yoksa_dugme_yok(): void
    {
        $kurum = $this->kurum();
        $this->basvuru($kurum, BasvuruDurumu::Gonderildi, BasvuruTuru::BasinMensubu);

        $this->detay($kurum)
            ->assertDontSee('Başvuruyu incele')
            ->assertDontSee('Başvuru detayına git');
    }
}
