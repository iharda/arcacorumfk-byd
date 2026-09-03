<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Notifications\BasvuruAlindi;
use App\Notifications\BasvuruReddedildi;
use App\Notifications\EksikEvrakTalebi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruNoUretici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Başvuru numarası: `2026-BV-0137` (Cüneyt Bey, 03.09.2026 — "kart no gibi
 * normal olacak, buna bir düzen koymamız lazım").
 *
 * 🔒 Numara e-postayla dışarı gidiyor ve müşteri telefonda okuyor: biçimi,
 * sırası ve benzersizliği testle sabitlenir. ULID'in yerini ALMADIĞI da
 * burada duruyor — biri "madem okunur numara var, rota da ondan olsun" derse
 * bu test hatırlatır.
 */
class BasvuruNoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function taslak(BasvuruTuru $tur = BasvuruTuru::Kurum): Basvuru
    {
        return Basvuru::create([
            'tur' => $tur,
            'durum' => BasvuruDurumu::Taslak,
            'basvuran_ad' => 'Yusuf Demir',
            'basvuran_eposta' => 'yusuf'.uniqid().'@ornek.test',
        ]);
    }

    private function gonderilmis(BasvuruTuru $tur = BasvuruTuru::Kurum): Basvuru
    {
        $basvuru = $this->taslak($tur);

        app(BasvuruAkisi::class)->gonder($basvuru);

        return $basvuru->refresh();
    }

    public function test_numara_kart_numarasiyla_ayni_bicimde(): void
    {
        $basvuru = $this->gonderilmis();

        $this->assertSame(
            now()->timezone('Europe/Istanbul')->year.'-BV-0001',
            $basvuru->basvuru_no,
        );
        $this->assertSame(1, preg_match('/^\d{4}-BV-\d{4}$/', $basvuru->basvuru_no));
    }

    /**
     * 🔑 T3'ün asıl kuralı: numara GÖNDERİM anında verilir. Taslak numara
     * yakarsa seride boşluk oluşur ve numara "sıralı" olma iddiasını kaybeder.
     */
    public function test_gonderilmeyen_basvuru_numara_almaz(): void
    {
        $taslak = $this->taslak();

        $this->assertNull($taslak->basvuru_no);
        $this->assertNull($taslak->no_sira);

        // Taslak dururken gönderilen başvuru İLK numarayı alır.
        $this->assertSame(
            now()->timezone('Europe/Istanbul')->year.'-BV-0001',
            $this->gonderilmis()->basvuru_no,
        );
    }

    public function test_numaralar_gonderim_sirasinca_ilerliyor(): void
    {
        $yil = now()->timezone('Europe/Istanbul')->year;

        $numaralar = collect(range(1, 5))->map(fn () => $this->gonderilmis()->basvuru_no);

        $this->assertSame([
            $yil.'-BV-0001', $yil.'-BV-0002', $yil.'-BV-0003',
            $yil.'-BV-0004', $yil.'-BV-0005',
        ], $numaralar->all());
    }

    /**
     * 💥 Düzeltmeden dönen başvuru yeniden gönderiliyor ama AYNI başvuru:
     * başvuranın elindeki numara değişirse e-postadaki numara artık hiçbir
     * kaydı göstermez.
     */
    public function test_duzeltmeden_donuste_numara_degismiyor(): void
    {
        $basvuru = $this->gonderilmis();
        $ilk = $basvuru->basvuru_no;

        $basvuru->update(['durum' => BasvuruDurumu::EksikEvrak]);
        app(BasvuruAkisi::class)->gonder($basvuru);

        $this->assertSame($ilk, $basvuru->refresh()->basvuru_no);
        $this->assertSame(BasvuruDurumu::YenidenInceleme, $basvuru->durum);
    }

    /**
     * 💣 Silinen başvurunun numarası yeniden dağıtılırsa arşivde iki farklı
     * başvuru aynı numarayı taşır.
     */
    public function test_silinen_basvurunun_numarasi_yeniden_dagitilmaz(): void
    {
        $silinen = $this->gonderilmis();
        $silinen->delete();

        $yeni = $this->gonderilmis();

        $this->assertNotSame($silinen->basvuru_no, $yeni->basvuru_no);
        $this->assertSame(2, $yeni->no_sira);
    }

    public function test_numaralar_benzersiz(): void
    {
        $numaralar = collect(range(1, 20))->map(fn () => $this->gonderilmis()->basvuru_no);

        $this->assertCount(20, $numaralar->unique());
    }

    /** Kod tek yerde: kart harfleri gibi ayardan gelmiyor, sabit "BV". */
    public function test_tur_kodu_tek_tanim(): void
    {
        $this->assertSame('BV', BasvuruNoUretici::KOD);
    }

    /** ULID hâlâ rota anahtarı: sıralı numara TAHMİN EDİLEBİLİR. */
    public function test_rota_anahtari_hala_ulid(): void
    {
        $basvuru = $this->gonderilmis();

        $this->assertSame('ulid', $basvuru->getRouteKeyName());
        $this->assertSame($basvuru->ulid, $basvuru->getRouteKey());
    }

    public function test_alindi_bildiriminde_numara_ve_duzgun_cumle_var(): void
    {
        $basvuru = $this->gonderilmis();

        $posta = (new BasvuruAlindi($basvuru))->toMail($basvuru);
        $govde = implode("\n", array_merge($posta->introLines, $posta->outroLines));

        $this->assertStringContainsString('Başvuru numaranız: **'.$basvuru->basvuru_no.'**', $govde);
        $this->assertStringNotContainsString($basvuru->ulid, $govde);

        // 💥 "Kurumsal başvuru başvurunuz" hatası geri gelmesin.
        $this->assertStringContainsString('**Medya kuruluşu** başvurunuz başarıyla alınmıştır.', $govde);
        $this->assertStringNotContainsString('başvuru başvurunuz', $govde);

        // İmza ve otomatik gönderim dipnotu (Cüneyt Bey revizyonu 03.09.2026).
        $this->assertStringContainsString('ARCA Çorum FK', (string) $posta->salutation);
        $this->assertStringContainsString('otomatik olarak gönderilmiştir', $posta->viewData['dipnot']);
    }

    /**
     * E8: numara yalnız panelde kalırsa telefonda "başvuru numaranız kaç?"
     * sorusunun cevabı yine olmaz. Üç bildirimin de taşıması gerekiyor.
     */
    public function test_eksik_evrak_ve_red_bildirimleri_de_numara_tasiyor(): void
    {
        $basvuru = $this->gonderilmis();
        $basvuru->update([
            'duzeltme_notlari' => ['evrak:kimlik_gorseli' => 'Okunmuyor.'],
            'karar_gerekcesi' => 'Belge geçersiz.',
        ]);

        foreach ([new EksikEvrakTalebi($basvuru, 'sahte-bilet-jetonu'), new BasvuruReddedildi($basvuru)] as $bildirim) {
            $posta = $bildirim->toMail($basvuru);
            $govde = implode("\n", array_merge($posta->introLines, $posta->outroLines));

            $this->assertStringContainsString($basvuru->basvuru_no, $govde);
        }
    }
}
