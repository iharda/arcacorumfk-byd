<?php

namespace Tests\Feature;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use Database\Seeders\EvrakTuruSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Doğrulama hatasında seçilen dosyalar kaybolmuyor -- Cüneyt Bey revizyonu
 * (03.09.2026): "ya formu yeniden yükletmemeliyiz, ya da yüklense bile
 * evrak seçimi yaptırmamalıyız."
 *
 * 💀 Bu testin koruduğu şey: başvuran formda TEK bir alanı yanlış doldurunca
 * kimliğini, fotoğrafını ve çalışma belgesini BAŞTAN seçmek zorunda kalıyordu.
 */
class EvrakTaslagiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EvrakTuruSeeder::class);
        Notification::fake();

        /*
         * 💀 TESTLER ÜRETİM DİSKİNE YAZIYORDU (05.09.2026). Tek tek testler
         * yalnızca `local`i sahteliyordu -- taslağın durduğu disk. Oysa başarılı
         * gönderim taslağı GERÇEK evraka çeviriyor ve `EvrakYukleyici` onu
         * `evrak` diskine yazıyor; o disk sahtelenmediği için her koşu canlı
         * `storage/app/evrak` altına 4 dosya bırakıyordu. Üç günde 322 yetim
         * dosya böyle birikti: kaydı olmayan, kimsenin silmediği çöp.
         *
         * 🔑 İki disk de setUp'ta sahtelenir: sınıfa yeni bir test eklendiğinde
         * kimse bunu yeniden hatırlamak zorunda kalmasın.
         */
        Storage::fake('local');
        Storage::fake(config('bys.evrak_disk'));
    }

    /** Gerçek bir PNG: yükleyici türü MAGIC BYTE'tan okuyor, uzantıya bakmıyor. */
    private function png(string $ad): UploadedFile
    {
        return UploadedFile::fake()->image($ad, 300, 300);
    }

    /** @return array<string, mixed> */
    private function form(array $ezme = []): array
    {
        return array_merge([
            'ad_soyad' => 'Aybers Polat',
            'eposta' => 'aybers+taslak@ornek.test',
            'telefon_ulke' => '+90',
            'telefon' => '532 100 85 50',
            'adres' => 'Bahçelievler Mahallesi 1. Sokak No 3',
            'il' => 'Çorum',
            'ilce' => 'Merkez',
            'sosyal_medya' => ['x' => 'aybers.com'],
            'basin_karti_var' => '1',
            'kvkk_aydinlatma' => '1',
            'kvkk_riza' => '1',
        ], $ezme);
    }

    /** @return array<int, UploadedFile> */
    private function dosyalar(): array
    {
        $turler = EvrakTuru::turIcin(BasvuruTuru::IcerikUreticisi);

        return $turler->mapWithKeys(fn (EvrakTuru $t) => [$t->id => $this->png($t->kod.'.png')])->all();
    }

    public function test_hatali_gonderimden_sonra_dosya_yeniden_istenmez(): void
    {
        // 1) Adres eksik: doğrulama patlar ama dosyalar seçilmişti.
        $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form(['adres' => '']) + [
            'evraklar' => $this->dosyalar(),
        ])->assertSessionHasErrors('adres');

        $this->assertSame(0, Basvuru::count(), 'Hatalı gönderim başvuru yaratmamalı.');

        // 2) Aynı oturumda hatayı düzeltip DOSYASIZ gönderiyoruz.
        $yanit = $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form());

        $yanit->assertSessionHasNoErrors();
        $yanit->assertRedirect(route('basvuru.gonderildi'));

        $basvuru = Basvuru::sole();

        $this->assertSame(2, $basvuru->evraklar()->count(),
            'Taslaktaki iki belge başvuruya bağlanmalıydı.');
        $this->assertSame(
            ['biyometrik_fotograf.png', 'kimlik_gorseli.png'],
            $basvuru->evraklar()->with('turu')->get()
                ->pluck('orijinal_ad')->sort()->values()->all(),
        );
    }

    /** Başarılı gönderimden sonra taslak diskte de oturumda da kalmamalı. */
    public function test_basarili_gonderim_taslagi_temizler(): void
    {
        $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form(['adres' => '']) + [
            'evraklar' => $this->dosyalar(),
        ])->assertSessionHasErrors('adres');

        $this->assertCount(2, Storage::disk('local')->files('evrak-taslagi'));

        $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form())
            ->assertRedirect(route('basvuru.gonderildi'));

        $this->assertCount(0, Storage::disk('local')->files('evrak-taslagi'));
        $this->assertNull(session('bys_evrak_taslagi'));
    }

    /**
     * 🔒 Taslak TÜRE GÖRE süzülür: kurum formunu yarım bırakıp bireysel forma
     * geçen başvuranın sicil gazetesi buraya sızmamalı. Sızsaydı
     * `BasvuruEvrakAlici` "geçersiz evrak türü" deyip başvuruyu komple
     * reddederdi.
     */
    public function test_baska_turun_taslagi_forma_sizmaz(): void
    {
        $kurumEvraklari = EvrakTuru::turIcin(BasvuruTuru::Kurum)
            ->mapWithKeys(fn (EvrakTuru $t) => [$t->id => $this->png($t->kod.'.png')])->all();

        // Kurum formu yarım kaldı: yüklenen her belge taslakta.
        // 🪤 Sayı SABİT YAZILMAZ: kurumsal başvurunun belge listesi büyüyebilir
        // (imza sirküleri M7'de eklendi) ve test o gün kırılırdı. Ölçülen şey
        // belge SAYISI değil, taslağın gerçekten yazılmış olması.
        $this->post(route('basvuru.kurum.kaydet'), ['evraklar' => $kurumEvraklari])
            ->assertSessionHasErrors('resmi_unvan');

        $this->assertCount(count($kurumEvraklari), Storage::disk('local')->files('evrak-taslagi'));

        // Aynı oturumda bireysel forma geçiliyor: kurum belgeleri sayılmamalı,
        // yani zorunlu belgeler HÂLÂ eksik.
        $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form())
            ->assertSessionHasErrors('evraklar.'.EvrakTuru::where('kod', 'kimlik_gorseli')->value('id'));

        $this->assertSame(0, Basvuru::count());
    }

    /** Süresi geçen taslaklar süpürülür (KVKK: aralarında kimlik belgesi var). */
    public function test_eski_taslaklar_temizlik_isinde_silinir(): void
    {
        $this->post(route('basvuru.icerik-ureticisi.kaydet'), $this->form(['adres' => '']) + [
            'evraklar' => $this->dosyalar(),
        ])->assertSessionHasErrors('adres');

        $this->assertCount(2, Storage::disk('local')->files('evrak-taslagi'));

        $this->travel(2)->days();
        $this->artisan('evrak:taslak-temizle')->assertSuccessful();

        $this->assertCount(0, Storage::disk('local')->files('evrak-taslagi'));
    }
}
