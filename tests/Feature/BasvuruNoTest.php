<?php

namespace Tests\Feature;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Notifications\BasvuruAlindi;
use App\Servisler\BasvuruNoUretici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kısa başvuru numarası (Yusuf/IT isteği, 2026-08-27).
 *
 * 🔒 Numara e-postayla dışarı gidiyor ve müşteri telefonda okuyor: uzunluğu,
 * alfabesi ve benzersizliği testle sabitlenir. ULID'in yerini ALMADIĞI da
 * burada duruyor — biri "madem kısa numara var, rota da ondan olsun" derse
 * bu test hatırlatır.
 */
class BasvuruNoTest extends TestCase
{
    use RefreshDatabase;

    private function basvuruYap(BasvuruTuru $tur = BasvuruTuru::Kurum): Basvuru
    {
        return Basvuru::create([
            'tur' => $tur,
            'durum' => BasvuruDurumu::Gonderildi,
            'basvuran_ad' => 'Yusuf Demir',
            'basvuran_eposta' => 'yusuf@ornek.test',
        ]);
    }

    public function test_kayit_aninda_dort_karakterli_numara_verilir(): void
    {
        $basvuru = $this->basvuruYap();

        $this->assertSame(4, strlen($basvuru->basvuru_no));
        $this->assertSame(1, preg_match(
            '/^['.BasvuruNoUretici::ALFABE.']{4}$/', $basvuru->basvuru_no,
        ));
    }

    /** Sesli harf yoksa üretilen dizi Türkçe bir kelimeye benzeyemez. */
    public function test_alfabede_sesli_harf_ve_karisan_karakter_yok(): void
    {
        foreach (['A', 'E', 'I', 'O', 'U', '0', '1', 'L'] as $yasak) {
            $this->assertStringNotContainsString($yasak, BasvuruNoUretici::ALFABE);
        }
    }

    public function test_numaralar_benzersiz(): void
    {
        $numaralar = collect(range(1, 40))->map(fn () => $this->basvuruYap()->basvuru_no);

        $this->assertCount(40, $numaralar->unique());
    }

    /** ULID hâlâ rota anahtarı: kısa numara yalnızca gösterim içindir. */
    public function test_rota_anahtari_hala_ulid(): void
    {
        $basvuru = $this->basvuruYap();

        $this->assertSame('ulid', $basvuru->getRouteKeyName());
        $this->assertSame($basvuru->ulid, $basvuru->getRouteKey());
    }

    public function test_alindi_bildiriminde_kisa_numara_ve_duzgun_cumle_var(): void
    {
        $basvuru = $this->basvuruYap();

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

    public function test_bireysel_turlerde_cumle_bozulmuyor(): void
    {
        $beklenenler = [
            [BasvuruTuru::BasinMensubu, '**Basın mensubu** başvurunuz'],
            [BasvuruTuru::IcerikUreticisi, '**Bağımsız içerik üreticisi** başvurunuz'],
        ];

        foreach ($beklenenler as [$tur, $beklenen]) {
            $basvuru = $this->basvuruYap($tur);
            $posta = (new BasvuruAlindi($basvuru))->toMail($basvuru);

            $this->assertStringContainsString($beklenen, implode("\n", $posta->introLines));
        }
    }
}
