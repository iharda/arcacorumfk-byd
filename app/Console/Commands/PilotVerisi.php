<?php

namespace App\Console\Commands;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Enums\CalisanAraligi;
use App\Enums\DeneyimAraligi;
use App\Jobs\KartUret;
use App\Models\Akreditasyon;
use App\Models\Antrenman;
use App\Models\Basvuru;
use App\Models\Bulten;
use App\Models\Duyuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Models\GecisKaydi;
use App\Models\KapiIstemcisi;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\EvrakYukleyici;
use App\Servisler\KapiIstemcisiAkisi;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Pilot / tanıtım verisi.
 *
 * Kulüp sistemi baştan sona gezebilsin diye gerçekçi bir örnek küme kurar:
 * akredite kurum, incelemeyi bekleyen başvurular, kartı basılmış akredite
 * kişiler, tanımlı kapı ve birkaç geçiş kaydı.
 *
 * 🔖 Ürettiği her kayıt `pilot` etiketiyle işaretlenir; `--sil` hepsini
 *    temizler. Gerçek veriye DOKUNMAZ.
 * ⚠️ Şifreler tanıtım içindir. Canlıya çıkmadan mutlaka silin.
 */
class PilotVerisi extends Command
{
    protected $signature = 'bys:pilot-verisi {--sil : Pilot kayıtlarını temizle}';

    protected $description = 'Tanıtım/pilot için örnek veri kurar veya siler';

    /** Pilot kayıtları bu son ekle tanınır. */
    private const ETIKET = '+pilot@ornek.test';

    private const SIFRE = 'Pilot-Deneme-2026';

    public function handle(): int
    {
        if ($this->option('sil')) {
            return $this->sil();
        }

        if (User::where('email', 'like', '%'.self::ETIKET)->exists()) {
            $this->warn('Pilot verisi zaten kurulu. Önce: php artisan bys:pilot-verisi --sil');

            return self::FAILURE;
        }

        $this->info('Pilot verisi kuruluyor…');

        $kurum = $this->kurum('Çorum Haber Ajansı', 'akredite');
        $this->kurum('Bozok Medya Grubu', 'beklemede');

        // Kurum yetkilisi — kurum panelini gezmek için
        $this->kullanici('yetkili', 'Nurten Aksoy', User::ROL_KURUM, $kurum);

        // Kartı basılmış iki akredite kişi
        $this->akrediteKisi('muhabir', 'Şükrü Ağaoğlu', BasvuruTuru::BasinMensubu, $kurum,
            ['saha_kenari', 'basin_locasi', 'karma_alan']);
        $this->akrediteKisi('kameraman', 'Elif Yıldırım', BasvuruTuru::BasinMensubu, $kurum,
            ['basin_locasi']);
        $this->akrediteKisi('bagimsiz', 'Cem Öztürk', BasvuruTuru::IcerikUreticisi, null,
            ['basin_toplanti_salonu']);

        // İnceleme kuyruğunda bekleyen başvurular — yetkili ekranı boş olmasın
        $this->bekleyenBasvuru('aday1', 'Merve Kılıç', BasvuruTuru::BasinMensubu, $kurum, BasvuruDurumu::Gonderildi);
        $this->bekleyenBasvuru('aday2', 'Onur Demirtaş', BasvuruTuru::IcerikUreticisi, null, BasvuruDurumu::Incelemede);

        $this->icerik();
        $this->kapi();

        $this->newLine();
        $this->info('✔ Pilot verisi hazır.');
        $this->table(['Ne', 'Nasıl girilir'], [
            ['Kurum paneli', 'yetkili'.self::ETIKET.'  ·  '.self::SIFRE],
            ['Üye paneli (kartlı)', 'muhabir'.self::ETIKET.'  ·  '.self::SIFRE],
            ['Üye paneli (bağımsız)', 'bagimsiz'.self::ETIKET.'  ·  '.self::SIFRE],
        ]);
        $this->warn('Kapı anahtarı yukarıda bir kez yazıldı; kaybolursa panelden yenileyin.');
        $this->warn('Canlıya çıkmadan: php artisan bys:pilot-verisi --sil');

        return self::SUCCESS;
    }

    private function kurum(string $unvan, string $durum): Kurum
    {
        return Kurum::create([
            'resmi_unvan' => $unvan,
            'adres' => 'Gazi Caddesi No: 12',
            'il' => 'Çorum',
            'ilce' => 'Merkez',
            'telefon' => '+90 364 213 45 67',
            'eposta' => 'iletisim'.self::ETIKET,
            'vergi_dairesi' => 'Çorum Vergi Dairesi',
            'vergi_no' => (string) random_int(1000000000, 9999999999),
            'calisan_araligi' => CalisanAraligi::sayidan(random_int(8, 40)),
            'yayin_platformlari' => [['ad' => $unvan, 'url' => 'https://ornek.test']],
            'akreditasyon_durumu' => $durum,
        ]);
    }

    private function kullanici(string $onek, string $ad, string $rol, ?Kurum $kurum): User
    {
        $u = User::create([
            'name' => $ad,
            'email' => $onek.self::ETIKET,
            'password' => Hash::make(self::SIFRE),
            'telefon' => $this->telefon(),
            'kurum_id' => $kurum?->id,
            'aktif' => true,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($rol);

        return $u;
    }

    private function telefon(): string
    {
        return '+90 5'.random_int(30, 59).' '.random_int(100, 999).' '.random_int(10, 99).' '.random_int(10, 99);
    }

    private function akrediteKisi(string $onek, string $ad, BasvuruTuru $tur, ?Kurum $kurum, array $bolgeler): void
    {
        $rol = $tur === BasvuruTuru::BasinMensubu ? User::ROL_BASIN : User::ROL_ICERIK;
        $kullanici = $this->kullanici($onek, $ad, $rol, $kurum);

        $basvuru = Basvuru::create([
            'tur' => $tur,
            'durum' => BasvuruDurumu::Onaylandi,
            'kullanici_id' => $kullanici->id,
            'kurum_id' => $kurum?->id,
            'basvuran_ad' => $ad,
            'basvuran_eposta' => $kullanici->email,
            'basvuran_telefon' => $kullanici->telefon,
            'gonderildi_at' => now()->subDays(random_int(3, 20)),
            'karar_at' => now()->subDays(random_int(1, 2)),
        ]);

        $this->evrak($basvuru, 'biyometrik_fotograf', 'foto.jpg', 'image/jpeg');
        $this->evrak($basvuru, 'kimlik_gorseli', 'kimlik.jpg', 'image/jpeg');

        $akreditasyon = app(AkreditasyonAkisi::class)->basvurudanOlustur($basvuru);
        $akreditasyon?->update(['bolge_yetkileri' => $bolgeler, 'sezon' => '2026 / 2027']);

        // Bölge yazıldıktan SONRA kart üret: bölgeler kartın üstünde yazıyor.
        if ($akreditasyon) {
            KartUret::dispatch($akreditasyon, bildirimGonder: false);
            $this->line("  · {$ad} — {$akreditasyon->kart_no}");
        }
    }

    /**
     * İncelemeyi bekleyen başvuru.
     *
     * 🔑 HESAPSIZ: hesap onay anında açılır (Revizyon md.1). Kullanıcı kaydı
     * üretmek, panele hiç giremeyecek yetim hesaplar bırakırdı.
     */
    private function bekleyenBasvuru(string $onek, string $ad, BasvuruTuru $tur, ?Kurum $kurum, BasvuruDurumu $durum): void
    {
        $basvuru = Basvuru::create([
            'tur' => $tur,
            'durum' => $durum,
            'kullanici_id' => null,
            'kurum_id' => $kurum?->id,
            'basvuran_ad' => $ad,
            'basvuran_eposta' => $onek.self::ETIKET,
            'basvuran_telefon' => $this->telefon(),
            'gonderildi_at' => now()->subDays(random_int(1, 4)),
            'form_verisi' => [
                'adres' => 'Pilot Mahallesi '.random_int(1, 40).'. Sokak No: '.random_int(1, 60),
                'il' => 'Çorum',
                'ilce' => 'Merkez',
                'basin_karti_var' => (bool) random_int(0, 1),
                'calisma_yili' => DeneyimAraligi::sayidan(random_int(1, 15))?->value,
            ],
        ]);

        $this->evrak($basvuru, 'biyometrik_fotograf', 'foto.jpg', 'image/jpeg');
        $this->evrak($basvuru, 'kimlik_gorseli', 'kimlik.jpg', 'image/jpeg');
        if ($tur === BasvuruTuru::BasinMensubu) {
            $this->evrak($basvuru, 'calisma_belgesi', 'calisma-belgesi.jpg', 'image/jpeg');
        }

        $this->line("  · {$ad} — {$durum->etiket()} (kuyrukta)");
    }

    private function evrak(Basvuru $basvuru, string $kod, string $dosya, string $mime): void
    {
        $kaynak = '/root/bys-test-dosyalari/'.$dosya;

        if (! is_file($kaynak)) {
            return;   // örnek dosyalar yoksa evraksız devam et
        }

        $tur = EvrakTuru::where('kod', $kod)->first();

        if ($tur) {
            app(EvrakYukleyici::class)->yukle($basvuru, $tur, new UploadedFile($kaynak, $dosya, $mime, null, true));
        }
    }

    private function icerik(): void
    {
        Duyuru::create([
            'baslik' => 'Basın mensupları için akreditasyon başvuruları açıldı',
            'ozet' => '2026/2027 sezonu akreditasyon başvuruları sistem üzerinden alınmaya başlandı.',
            'icerik' => '<p>Sezon boyunca kulüp tesislerine ve maçlara erişim için akreditasyon zorunludur.</p>',
            'yayinda' => true, 'yayin_at' => now()->subDays(2), 'bildirim_gonderildi' => true,
        ]);

        Bulten::create([
            'baslik' => 'Teknik direktör açıklamaları — hafta içi antrenman programı',
            'icerik' => '<p>Takımımız hafta boyunca çift idmanla hazırlıklarını sürdürecek.</p>',
            'yayinda' => true, 'yayin_at' => now()->subDay(), 'bildirim_gonderildi' => true,
        ]);

        foreach ([2, 4, 6] as $gun) {
            Antrenman::create([
                'baslik' => 'Hazırlık antrenmanı',
                'baslangic_at' => now()->addDays($gun)->setTime(10, 30),
                'bitis_at' => now()->addDays($gun)->setTime(12, 0),
                'yer' => 'Nazmi Avluca Tesisleri',
                'basina_acik' => $gun !== 4,
                'not' => $gun === 4 ? 'Bu antrenman basına kapalıdır.' : 'İlk 15 dakika görüntü alınabilir.',
                'yayinda' => true, 'yayin_at' => now(), 'bildirim_gonderildi' => true,
            ]);
        }

        $this->line('  · duyuru, bülten ve 3 antrenman eklendi');
    }

    private function kapi(): void
    {
        $sonuc = app(KapiIstemcisiAkisi::class)->olustur([
            'ad' => 'Pilot kapı — kuzey turnike',
            'kapi_kodu' => 'PILOT-KUZEY',
            'bolgeler' => ['saha_kenari', 'basin_locasi', 'karma_alan'],
        ]);

        $this->newLine();
        $this->line('  · Kapı anahtarı (/kapi adresine girilecek):');
        $this->line('    <fg=yellow>'.$sonuc['anahtar'].'</>');
    }

    private function sil(): int
    {
        $sayac = 0;

        // 🪤 Bekleyen başvuruların KULLANICISI YOK: yalnızca hesaplardan
        //    yürüyen temizlik onları geride bırakırdı.
        $hesapsiz = Basvuru::withTrashed()
            ->whereNull('kullanici_id')
            ->where('basvuran_eposta', 'like', '%'.self::ETIKET)
            ->pluck('id');

        Evrak::withTrashed()->whereIn('basvuru_id', $hesapsiz)->get()->each->forceDelete();
        Basvuru::withTrashed()->whereIn('id', $hesapsiz)->forceDelete();

        foreach (User::withTrashed()->where('email', 'like', '%'.self::ETIKET)->get() as $u) {
            $basvuruIdleri = Basvuru::withTrashed()->where('kullanici_id', $u->id)->pluck('id');

            Akreditasyon::whereIn('basvuru_id', $basvuruIdleri)->get()->each(function (Akreditasyon $a) {
                $a->kartlar()->get()->each->delete();   // dosyaları da siler
                $a->gecisKayitlari()->delete();
                $a->delete();
            });

            Evrak::withTrashed()->whereIn('basvuru_id', $basvuruIdleri)->get()->each->forceDelete();
            Basvuru::withTrashed()->whereIn('id', $basvuruIdleri)->forceDelete();

            $kurum = $u->kurum;
            $u->forceDelete();
            $kurum?->forceDelete();
            $sayac++;
        }

        Kurum::withTrashed()->whereIn('resmi_unvan', ['Çorum Haber Ajansı', 'Bozok Medya Grubu'])
            ->get()->each->forceDelete();

        GecisKaydi::where('kapi_kodu', 'PILOT-KUZEY')->delete();
        KapiIstemcisi::withTrashed()->where('kapi_kodu', 'PILOT-KUZEY')->forceDelete();

        Duyuru::where('baslik', 'like', '%akreditasyon başvuruları açıldı%')->forceDelete();
        Bulten::where('baslik', 'like', 'Teknik direktör açıklamaları%')->forceDelete();
        Antrenman::where('yer', 'Nazmi Avluca Tesisleri')->forceDelete();

        $this->info("✔ Pilot verisi silindi ({$sayac} kullanıcı).");
        $this->line('Denetim kaydı BİLEREK silinmedi — değiştirilemez bir kayıttır.');

        return self::SUCCESS;
    }
}
