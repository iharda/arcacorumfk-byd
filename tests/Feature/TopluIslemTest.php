<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\DenetimKaydi;
use App\Models\User;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\BasvuruAkisi;
use App\Support\TopluIslem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Toplu işlemler -- saha notları E4.
 *
 * 🔒 Korunan asıl kural: denetim kaydı SATIR SATIR yazılır. Biri performans
 * için döngüyü tek sorguya çevirmeye kalkarsa ("hepsini bir update ile
 * askıya alalım") bu test durdurur — altı ay sonra "bu kartı kim askıya
 * aldı" sorusunun cevabı kalmazdı.
 */
class TopluIslemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function akreditasyon(int $sira, AkreditasyonDurumu $durum): Akreditasyon
    {
        $kullanici = User::create([
            'name' => 'Aday '.$sira,
            'email' => 'aday'.$sira.'@ornek.test',
            'password' => bcrypt('x'),
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => $kullanici->email,
        ]);

        return Akreditasyon::create([
            'basvuru_id' => $basvuru->id,
            'kart_no' => sprintf('2026-B-%04d', $sira),
            'yil' => 2026,
            'tur_kodu' => 'B',
            'sira' => $sira,
            'kullanici_id' => $kullanici->id,
            'durum' => $durum,
        ]);
    }

    public function test_her_satir_kendi_denetim_kaydini_yazar(): void
    {
        $kayitlar = collect([
            $this->akreditasyon(1, AkreditasyonDurumu::Aktif),
            $this->akreditasyon(2, AkreditasyonDurumu::Aktif),
        ]);

        TopluIslem::calistir(
            $kayitlar,
            '%d akreditasyon askıya alındı.',
            fn (Akreditasyon $a) => app(AkreditasyonAkisi::class)->askiyaAl($a, 'Sezon sonu.'),
            fn (Akreditasyon $a) => $a->durum === AkreditasyonDurumu::Aktif,
        );

        // Tek "toplu islem yapildi" kaydi DEGIL: satir basina bir kayit.
        $this->assertSame(2, DenetimKaydi::query()
            ->where('olay', 'akreditasyon.askiya_alindi')->count());

        foreach ($kayitlar as $a) {
            $this->assertSame(AkreditasyonDurumu::Askida, $a->refresh()->durum);
        }
    }

    /** Seçimde hepsi işaretlenir; uygun olmayan satır sessizce atlanır. */
    public function test_uygun_olmayan_satir_atlanir(): void
    {
        $aktif = $this->akreditasyon(1, AkreditasyonDurumu::Aktif);
        $iptal = $this->akreditasyon(2, AkreditasyonDurumu::Iptal);

        TopluIslem::calistir(
            collect([$aktif, $iptal]),
            '%d akreditasyon askıya alındı.',
            fn (Akreditasyon $a) => app(AkreditasyonAkisi::class)->askiyaAl($a, 'Sezon sonu.'),
            fn (Akreditasyon $a) => $a->durum === AkreditasyonDurumu::Aktif,
        );

        $this->assertSame(AkreditasyonDurumu::Askida, $aktif->refresh()->durum);
        $this->assertSame(AkreditasyonDurumu::Iptal, $iptal->refresh()->durum);
    }

    /**
     * 🪤 Hepsi TEK işlemde değil: bir satır patlarsa diğerleri geri sarılmaz.
     * Yüz satırın doksan dokuzu geçip biri patlayınca yetkili baştan başlamaz.
     */
    public function test_bir_satirdaki_hata_digerlerini_durdurmaz(): void
    {
        $ilk = $this->akreditasyon(1, AkreditasyonDurumu::Aktif);
        $patlayan = $this->akreditasyon(2, AkreditasyonDurumu::Aktif);
        $son = $this->akreditasyon(3, AkreditasyonDurumu::Aktif);

        TopluIslem::calistir(
            collect([$ilk, $patlayan, $son]),
            '%d akreditasyon askıya alındı.',
            function (Akreditasyon $a) use ($patlayan) {
                if ($a->is($patlayan)) {
                    throw new RuntimeException('Deneme hatası.');
                }

                app(AkreditasyonAkisi::class)->askiyaAl($a, 'Sezon sonu.');
            },
        );

        $this->assertSame(AkreditasyonDurumu::Askida, $ilk->refresh()->durum);
        $this->assertSame(AkreditasyonDurumu::Aktif, $patlayan->refresh()->durum);
        $this->assertSame(AkreditasyonDurumu::Askida, $son->refresh()->durum);
    }

    /** Başvuru tarafı: iptal yalnız KUYRUKTAKİ başvurulardan yapılabilir. */
    public function test_basvuru_iptalinde_karara_baglanmis_satir_atlanir(): void
    {
        $kuyrukta = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_ad' => 'Kuyruktaki',
            'basvuran_eposta' => 'kuyruk@ornek.test',
            'gonderildi_at' => now()->subDays(20),
        ]);

        $onayli = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'basvuran_ad' => 'Onaylı',
            'basvuran_eposta' => 'onayli@ornek.test',
            'gonderildi_at' => now()->subDays(20),
        ]);

        TopluIslem::calistir(
            collect([$kuyrukta, $onayli]),
            '%d başvuru iptal edildi.',
            fn (Basvuru $b) => app(BasvuruAkisi::class)->iptalEt($b, 'Sezon kapandı.'),
            fn (Basvuru $b) => in_array($b->durum, BasvuruDurumu::kuyruk(), true),
        );

        $this->assertSame(BasvuruDurumu::IptalEdildi, $kuyrukta->refresh()->durum);
        $this->assertSame(BasvuruDurumu::Onaylandi, $onayli->refresh()->durum);
    }

    /**
     * T4: bekleme süresi cümlesi panoda ve kuyruk listesinde aynı kaynaktan
     * gelir; karara bağlanmış başvuruda hiç yazılmaz.
     */
    public function test_bekleme_suresi_yalniz_kuyruktaki_basvuruda_var(): void
    {
        $bekleyen = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_eposta' => 'bekleyen@ornek.test',
            'gonderildi_at' => now()->subDays(14),
        ]);

        $bugun = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_eposta' => 'bugun@ornek.test',
            'gonderildi_at' => now(),
        ]);

        $kararaBaglanan = Basvuru::create([
            'tur' => BasvuruTuru::IcerikUreticisi,
            'durum' => BasvuruDurumu::Onaylandi,
            'basvuran_eposta' => 'onaylandi@ornek.test',
            'gonderildi_at' => now()->subDays(14),
        ]);

        $this->assertSame(14, $bekleyen->bekleyenGun());
        $this->assertSame('14 gündür kuyrukta', $bekleyen->bekleyenSure());
        $this->assertSame('bugün geldi', $bugun->bekleyenSure());
        $this->assertNull($kararaBaglanan->bekleyenSure());
    }
}
