<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruAkisi;
use Database\Seeders\EvrakTuruSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * İmza sirküleri ve zorunluluğun yürürlük tarihi -- Tutarsızlık incelemesi M7.
 *
 * 💀 Korunan asıl davranış İKİNCİSİ: yeni bir belgeyi zorunlu yapmak YOLDAKİ
 * başvuruları kilitlememeli. Düzeltme bileti yalnız yetkilinin işaretlediği
 * alanları açtığı için, kuyruktaki başvuran yeni istenen belgeyi YÜKLEYEMEZ
 * bile -- "Eksik zorunlu evrak" hatasıyla çıkmaz sokağa girer.
 */
class ImzaSirkuleriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EvrakTuruSeeder::class);
        Notification::fake();
    }

    private function akis(): BasvuruAkisi
    {
        return app(BasvuruAkisi::class);
    }

    /** Kurumsal başvuruda türün zorunlu olarak tanımlı olduğunu doğrular. */
    public function test_imza_sirkuleri_kurumsal_basvuruda_zorunlu(): void
    {
        $tur = EvrakTuru::where('kod', 'imza_sirkuleri')->sole();

        $this->assertTrue($tur->zorunlu);
        $this->assertContains(BasvuruTuru::Kurum->value, $tur->basvuru_turleri);
        $this->assertNotContains(BasvuruTuru::BasinMensubu->value, $tur->basvuru_turleri);
    }

    /**
     * 🔒 Migration'ın yürürlük tarihi GELECEKTE olmalı.
     *
     * Canlıda 04.09.2026'da 14 kurum kaydı açılmıştı; "bugün" yazmak o gün
     * açılan başvuruları da kuralın içinde bırakır ve gönderim yapamaz hâle
     * getirirdi. Bu test o tarihi sabitler.
     */
    public function test_yururluk_tarihi_gelecekte_baslar(): void
    {
        $tur = EvrakTuru::where('kod', 'imza_sirkuleri')->sole();

        $this->assertNotNull($tur->zorunlu_baslangic);
        $this->assertTrue(
            $tur->zorunlu_baslangic->gt(now()),
            'Yürürlük tarihi gelecekte olmalı; yoksa mevcut başvurular kilitlenir.',
        );
    }

    private function kurumBasvurusu(): Basvuru
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Taslak,
            'basvuran_eposta' => 'iletisim@ornek.test',
        ]);

        // İmza sirküleri DIŞINDA kalan zorunlu belgeler yüklenmiş olsun.
        foreach (EvrakTuru::turIcin(BasvuruTuru::Kurum)->where('zorunlu', true) as $tur) {
            if ($tur->kod === 'imza_sirkuleri') {
                continue;
            }

            Evrak::create([
                'basvuru_id' => $basvuru->id,
                'evrak_turu_id' => $tur->id,
                'disk' => 'evrak',
                'yol' => 'basvuru/x/'.uniqid().'.pdf',
                'orijinal_ad' => 'belge.pdf',
                'mime' => 'application/pdf',
                'boyut' => 1024,
                'sifreli' => false,
            ]);
        }

        return $basvuru;
    }

    /** Yürürlüğe girdikten sonra imza sirkülersiz kurumsal başvuru durmalı. */
    public function test_imza_sirkuleri_olmadan_gonderim_durur(): void
    {
        $basvuru = $this->kurumBasvurusu();

        // Kural yürürlükte: bu başvuru tarihinden önce başlamış.
        EvrakTuru::where('kod', 'imza_sirkuleri')
            ->update(['zorunlu_baslangic' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('İmza sirküleri');

        $this->akis()->gonder($basvuru);
    }

    /**
     * 🔒 EN ÖNEMLİ KORUMA: yürürlük tarihi gelecekteyse KUYRUKTAKİ başvuru
     * kilitlenmez. Bu olmadan canlıda bekleyen 13 kurumsal başvuru yeniden
     * gönderim yapamaz hâle gelirdi.
     */
    public function test_yururluk_tarihi_sonraki_ise_yoldaki_basvuru_kilitlenmez(): void
    {
        $basvuru = $this->kurumBasvurusu();

        EvrakTuru::where('kod', 'imza_sirkuleri')
            ->update(['zorunlu_baslangic' => now()->addDay()->toDateString()]);

        $this->akis()->gonder($basvuru);

        $this->assertSame(BasvuruDurumu::Gonderildi, $basvuru->refresh()->durum);
    }

    /** Yürürlük tarihi geçmişte ise kural yine işler. */
    public function test_yururluk_tarihi_gecmiste_ise_zorunluluk_isler(): void
    {
        $basvuru = $this->kurumBasvurusu();

        EvrakTuru::where('kod', 'imza_sirkuleri')
            ->update(['zorunlu_baslangic' => now()->subMonth()->toDateString()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('İmza sirküleri');

        $this->akis()->gonder($basvuru);
    }

    /**
     * 💀 ONAYLANMIŞ ESKİ BAŞVURUDA "BEKLİYOR" YAZMAMALI.
     *
     * Kurum kendi panelindeki "Başvurum" ekranında o anki BÜTÜN evrak
     * türlerini görüyordu. İmza sirküleri eklenince 24.08'de onaylanmış bir
     * kurum, hiç istenmemiş bir belgeyi "Bekliyor" diye gördü -- üstelik
     * yükleyebileceği bir yer de yok (yükleme yalnız düzeltme biletiyle
     * açılır). Çıkmaz sokak ve yanlış bilgi.
     */
    public function test_sonradan_zorunlu_olan_belge_eski_basvuruda_gorunmez(): void
    {
        // Kural yarından yürürlükte (migration'daki gibi).
        EvrakTuru::where('kod', 'imza_sirkuleri')
            ->update(['zorunlu_baslangic' => now()->addDay()->toDateString()]);

        $basvuru = $this->kurumBasvurusu();
        $basvuru->forceFill(['durum' => BasvuruDurumu::Onaylandi])->save();

        $turler = EvrakTuru::turIcin(BasvuruTuru::Kurum)
            ->filter(fn (EvrakTuru $t) => $basvuru->evraklar->contains('evrak_turu_id', $t->id)
                || $t->basvuruIcinZorunluMu($basvuru));

        $this->assertFalse(
            $turler->contains('kod', 'imza_sirkuleri'),
            'Bu başvuruda istenmemiş belge listeye girmemeli.',
        );

        // Gerçekten yüklenmiş belgeler görünmeye devam etmeli.
        $this->assertTrue($turler->contains('kod', 'ticaret_sicil_gazetesi'));
    }

    /** Yüklenmişse görünür: sonradan zorunlu olsa bile kayıt kaybolmaz. */
    public function test_yuklenmis_belge_her_halukarda_gorunur(): void
    {
        $basvuru = $this->kurumBasvurusu();

        $imza = EvrakTuru::where('kod', 'imza_sirkuleri')->sole();
        $imza->update(['zorunlu_baslangic' => now()->addYear()->toDateString()]);

        Evrak::create([
            'basvuru_id' => $basvuru->id,
            'evrak_turu_id' => $imza->id,
            'disk' => 'evrak',
            'yol' => 'basvuru/x/imza.pdf',
            'orijinal_ad' => 'imza.pdf',
            'mime' => 'application/pdf',
            'boyut' => 1024,
            'sifreli' => false,
        ]);

        $basvuru->load('evraklar');

        $turler = EvrakTuru::turIcin(BasvuruTuru::Kurum)
            ->filter(fn (EvrakTuru $t) => $basvuru->evraklar->contains('evrak_turu_id', $t->id)
                || $t->basvuruIcinZorunluMu($basvuru));

        $this->assertTrue($turler->contains('kod', 'imza_sirkuleri'));
    }

    /** Bireysel başvuru bu belgeden hiç etkilenmemeli. */
    public function test_bireysel_basvuru_imza_sirkulerinden_etkilenmez(): void
    {
        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Taslak,
            'basvuran_eposta' => 'muhabir@ornek.test',
        ]);

        foreach (EvrakTuru::turIcin(BasvuruTuru::BasinMensubu)->where('zorunlu', true) as $tur) {
            Evrak::create([
                'basvuru_id' => $basvuru->id,
                'evrak_turu_id' => $tur->id,
                'disk' => 'evrak',
                'yol' => 'basvuru/x/'.uniqid().'.pdf',
                'orijinal_ad' => 'belge.pdf',
                'mime' => 'application/pdf',
                'boyut' => 1024,
                'sifreli' => false,
            ]);
        }

        $this->akis()->gonder($basvuru);

        $this->assertSame(BasvuruDurumu::Gonderildi, $basvuru->refresh()->durum);
    }
}
