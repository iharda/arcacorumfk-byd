<?php

namespace Tests\Feature;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\DenetimKaydi;
use App\Models\User;
use App\Servisler\AyarlarAkisi;
use App\Servisler\KapiIstemcisiAkisi;
use Database\Seeders\AyarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Sistem ayarları -- Plan v1.0 md.8, md.10.
 *
 * 🔒 İki şey korunuyor:
 *   1. Kullanımda olan bölge SİLİNEMEZ. Ekran bunu bir yardım metninde
 *      söylüyordu ama kod izin veriyordu; uyarı artık bir güvence.
 *   2. Her ayar değişikliği denetim kaydına eski → yeni değeriyle düşer.
 */
class AyarlarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AyarSeeder::class);

        $this->actingAs(User::create([
            'name' => 'Yetkili', 'email' => 'yetkili@ornek.test', 'password' => bcrypt('x'),
        ]));
    }

    /** Formun gönderdiği ham durum. */
    private function form(array $degisiklik = []): array
    {
        $bolgeler = collect((array) Ayar::al('bolgeler', []))
            ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
            ->values()->all();

        return array_merge([
            'kurum_teyidi_istensin' => (bool) Ayar::al('kurum_teyidi_istensin', false),
            'davet_gecerlilik_gun' => (int) Ayar::al('davet_gecerlilik_gun', 7),
            'duzeltme_bileti_gun' => (int) Ayar::al('duzeltme_bileti_gun', 14),
            'yeniden_basvuru_bekleme_gun' => (int) Ayar::al('yeniden_basvuru_bekleme_gun', 0),
            'kart_yil' => Ayar::al('kart_yil'),
            'mukerrer_okutma_saniye' => (int) Ayar::al('mukerrer_okutma_saniye', 30),
            'kart_paylasimi_saniye' => (int) Ayar::al('kart_paylasimi_saniye', 120),
            'kart_kodu_basin' => 'K',
            'kart_kodu_icerik' => 'B',
            'bolgeler' => $bolgeler,
            'varsayilan_bolgeler' => (array) Ayar::al('varsayilan_bolgeler', []),
        ], $degisiklik);
    }

    private function akreditasyon(array $bolgeler): Akreditasyon
    {
        $kullanici = User::create([
            'name' => 'Aday', 'email' => 'aday@ornek.test', 'password' => bcrypt('x'),
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::BasinMensubu,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'basvuran_eposta' => $kullanici->email,
        ]);

        return Akreditasyon::create([
            'basvuru_id' => $basvuru->id,
            'kullanici_id' => $kullanici->id,
            'kart_no' => '2026-K-0001',
            'yil' => 2026,
            'tur_kodu' => 'K',
            'sira' => 1,
            'durum' => AkreditasyonDurumu::Aktif,
            'bolge_yetkileri' => $bolgeler,
        ]);
    }

    /** Bölgesiz kart HER KAPIDAN geçer: sessizce silmek yetki genişletir. */
    public function test_karta_atanmis_bolge_silinemez(): void
    {
        $this->akreditasyon(['saha_kenari']);

        $kalanlar = collect((array) Ayar::al('bolgeler', []))
            ->reject(fn ($ad, $anahtar) => $anahtar === 'saha_kenari')
            ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
            ->values()->all();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1 akreditasyon');

        app(AyarlarAkisi::class)->kaydet($this->form(['bolgeler' => $kalanlar]));
    }

    public function test_kapiya_atanmis_bolge_silinemez(): void
    {
        app(KapiIstemcisiAkisi::class)->olustur([
            'ad' => 'Kuzey turnike', 'kapi_kodu' => 'KUZEY-1',
            'ip_listesi' => null, 'bolgeler' => ['basin_locasi'], 'aktif' => true,
        ]);

        $kalanlar = collect((array) Ayar::al('bolgeler', []))
            ->reject(fn ($ad, $anahtar) => $anahtar === 'basin_locasi')
            ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
            ->values()->all();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1 kapı');

        app(AyarlarAkisi::class)->kaydet($this->form(['bolgeler' => $kalanlar]));
    }

    /**
     * Varsayılan listede duran bölge de kullanımdadır: silinirse yeni
     * akreditasyonlar var olmayan bir anahtarla doğar.
     */
    public function test_varsayilan_listedeki_bolge_silinemez(): void
    {
        Ayar::yaz('varsayilan_bolgeler', ['karma_alan']);

        $kalanlar = collect((array) Ayar::al('bolgeler', []))
            ->reject(fn ($ad, $anahtar) => $anahtar === 'karma_alan')
            ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
            ->values()->all();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('varsayılanı');

        app(AyarlarAkisi::class)->kaydet($this->form(['bolgeler' => $kalanlar]));
    }

    public function test_kullanilmayan_bolge_silinebiliyor(): void
    {
        Ayar::yaz('varsayilan_bolgeler', []);

        $kalanlar = collect((array) Ayar::al('bolgeler', []))
            ->reject(fn ($ad, $anahtar) => $anahtar === 'karma_alan')
            ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
            ->values()->all();

        app(AyarlarAkisi::class)->kaydet($this->form(['bolgeler' => $kalanlar]));

        $this->assertArrayNotHasKey('karma_alan', (array) Ayar::al('bolgeler'));
    }

    /** Kapı eşikleri artık ekrandan yönetiliyor; kaydedildiğini sabitliyoruz. */
    public function test_kapi_esikleri_kaydediliyor_ve_denetime_dusuyor(): void
    {
        app(AyarlarAkisi::class)->kaydet($this->form([
            'mukerrer_okutma_saniye' => 45,
            'kart_paylasimi_saniye' => 0,
        ]));

        $this->assertSame(45, Ayar::al('mukerrer_okutma_saniye'));
        $this->assertSame(0, Ayar::al('kart_paylasimi_saniye'));

        $kayit = DenetimKaydi::where('olay', 'ayar.degistirildi')
            ->get()
            ->firstWhere(fn (DenetimKaydi $d) => array_key_exists('mukerrer_okutma_saniye', $d->yeni ?? []));

        $this->assertNotNull($kayit);
        $this->assertSame(30, $kayit->eski['mukerrer_okutma_saniye']);
        $this->assertSame(45, $kayit->yeni['mukerrer_okutma_saniye']);
    }

    /** Boş bırakılan kart yılı "içinde bulunulan yıl" demek: 0 değil null. */
    public function test_bos_kart_yili_null_olarak_yaziliyor(): void
    {
        app(AyarlarAkisi::class)->kaydet($this->form(['kart_yil' => 2027]));
        $this->assertSame(2027, Ayar::al('kart_yil'));

        app(AyarlarAkisi::class)->kaydet($this->form(['kart_yil' => '']));
        $this->assertNull(Ayar::al('kart_yil'));
    }

    /** Değişmeyen ayar denetim kaydını şişirmesin. */
    public function test_degisiklik_yoksa_denetim_kaydi_yazilmiyor(): void
    {
        // İlk kayıt tabloda karşılığı olmayan ayarları yazar; asıl kural
        // ikincisinde görünür: aynı durum ikinci kez yazılmamalı.
        app(AyarlarAkisi::class)->kaydet($this->form());
        $ilk = DenetimKaydi::where('olay', 'ayar.degistirildi')->count();

        app(AyarlarAkisi::class)->kaydet($this->form());

        $this->assertSame($ilk, DenetimKaydi::where('olay', 'ayar.degistirildi')->count());
    }

    /**
     * 💀 Tohumlama `updateOrCreate` ile DEĞERİ de eziyordu: kulüp panelden
     * kurum teyidini kapatıp elle bir `db:seed` çalıştırdığında ayar sessizce
     * varsayılana dönüyordu. Tohumlama ayarın VAR OLMASINI sağlar, değerini
     * sahiplenmez.
     */
    public function test_tohumlama_paneldeki_degeri_ezmiyor(): void
    {
        Ayar::yaz('kurum_teyidi_istensin', true);
        Ayar::yaz('mukerrer_okutma_saniye', 45);

        $this->seed(AyarSeeder::class);

        $this->assertTrue(Ayar::al('kurum_teyidi_istensin'));
        $this->assertSame(45, Ayar::al('mukerrer_okutma_saniye'));
    }

    /** Ayar tablosu sistemdeki ayarların TAM listesi olmalı. */
    public function test_koddan_okunan_her_ayarin_tablo_kaydi_var(): void
    {
        $beklenen = [
            'kurum_teyidi_istensin', 'davet_gecerlilik_gun', 'duzeltme_bileti_gun',
            'yeniden_basvuru_bekleme_gun', 'kart_yil', 'kart_tur_kodlari',
            'mukerrer_okutma_saniye', 'kart_paylasimi_saniye', 'bolgeler', 'varsayilan_bolgeler',
        ];

        $tabloda = Ayar::pluck('anahtar')->all();

        foreach ($beklenen as $anahtar) {
            $this->assertContains($anahtar, $tabloda, $anahtar.' ayarının tablo kaydı yok.');
        }
    }
}
