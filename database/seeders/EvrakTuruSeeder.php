<?php

namespace Database\Seeders;

use App\Enums\BasvuruTuru;
use App\Models\EvrakTuru;
use Illuminate\Database\Seeder;

/**
 * Evrak turleri -- Plan v1.0 md.3.1 / 3.2 / 3.3.
 * Bunlar VERI; yetkili panelden ekleyip cikarabilir (Faz 2 form olusturucuya zemin).
 *
 * 🔤 `ad` ve `aciklama` BASVURANIN GORDUGU metinlerdir (Cuneyt Bey revizyonu
 * 03.09.2026): baslik belgenin adi, aciklama hangi belgelerin kabul edildigi.
 * 🪤 `aciklama` eskiden IC NOT tutuyordu ("At-rest sifreli saklanir…") ve
 * hicbir ekranda basilmiyordu; artik forma ciktigi icin o notlar kod
 * yorumuna tasindi.
 */
class EvrakTuruSeeder extends Seeder
{
    public function run(): void
    {
        $kurum = BasvuruTuru::Kurum->value;
        $basin = BasvuruTuru::BasinMensubu->value;
        $icerik = BasvuruTuru::IcerikUreticisi->value;

        /*
         * 🪤 webp: config/bys.php'deki `mime_izin` listesinde SERBESTTİ ama
         * hiçbir evrak türünde kabul edilmiyordu -- ölü izin. Telefondan
         * paylaşılan görseller giderek webp oluyor ve kullanıcı yüklemesi
         * sessizce reddedilince sebebini anlamıyordu. İki liste artık aynı
         * şeyi söylüyor; {@see \Tests\Feature\DosyaTuruListeleriTest}
         * ayrışmayı bir daha yakalar. (Saha notları S6.)
         */
        $turler = [
            [
                'kod' => 'ticaret_sicil_gazetesi', 'ad' => 'Ticaret Sicili Gazetesi',
                'basvuru_turleri' => [$kurum], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 10,
            ],
            [
                /*
                 * M7: kurumsal başvurunun üçüncü zorunlu belgesi.
                 * 🪤 `zorunlu_baslangic` BURADA yok (null): sıfırdan kurulan
                 * sistemde kuyruk da yoktur. Canlıda yürürlük tarihi
                 * migration'dan geliyor (2026_09_04_190000) ki yoldaki
                 * başvurular "Eksik zorunlu evrak" ile kilitlenmesin.
                 */
                'kod' => 'imza_sirkuleri', 'ad' => 'İmza sirküleri',
                'aciklama' => 'Yetkiliyi temsile yetkili kıldığını gösteren, '
                    .'geçerli imza sirküleri veya imza beyannamesi',
                'basvuru_turleri' => [$kurum], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 15,
            ],
            [
                'kod' => 'vergi_levhasi', 'ad' => 'Vergi levhası',
                'basvuru_turleri' => [$kurum], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 20,
            ],
            [
                'kod' => 'biyometrik_fotograf', 'ad' => 'Biyometrik fotoğraf',
                'aciklama' => 'Basın kartınızda ve kapı doğrulama ekranında kullanılır.',
                'basvuru_turleri' => [$basin, $icerik], 'zorunlu' => true,
                'izinli_formatlar' => ['jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 5120,
                'hassas' => false, 'sira' => 30,
            ],
            [
                // 🔒 At-rest sifreli saklanir; karar sonrasi imha planina girer.
                'kod' => 'kimlik_gorseli', 'ad' => 'Kimlik belgesi',
                'aciklama' => 'T.C. kimlik kartı, sürücü belgesi veya pasaport',
                'basvuru_turleri' => [$basin, $icerik], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => true, 'imha_gun' => 180, 'sira' => 40,
            ],
            [
                // 🔑 Kurum teyidi ayari kapaliyken yetkili bu belgeden dogrular.
                'kod' => 'calisma_belgesi', 'ad' => 'Çalışma belgesi',
                'aciklama' => 'Çalışma belgesi, işe giriş bildirgesi veya güncel SGK belgesi',
                'basvuru_turleri' => [$basin], 'zorunlu' => true,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => true, 'imha_gun' => 180, 'sira' => 50,
            ],
            /*
             * Ek talep kabi -- Yusuf revizyonu 25.08.2026. Yetkilinin alan
             * listemizde OLMAYAN bir belge istemesi bu tur uzerinden yurur.
             *
             * 🔑 `basvuru_turleri` BOS ve `aktif` FALSE: `EvrakTuru::turIcin()`
             * bunu hicbir zaman dondurmez, yani normal basvuru formunda ve
             * isaretlenebilir alanlar listesinde GORUNMEZ. Yalnizca
             * BasvuruDuzeltmeController ek talep yuklerken koda gore bulur.
             * Hangi belge oldugu `evraklar.ek_etiket` sutununda durur.
             */
            [
                'kod' => 'ek_belge', 'ad' => 'Ek talep belgesi',
                'aciklama' => 'Yetkilinin duzeltme talebinde elle istedigi belge.',
                'basvuru_turleri' => [], 'zorunlu' => false,
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 'maks_boyut_kb' => 8192,
                'hassas' => false, 'sira' => 900, 'aktif' => false,
            ],
        ];

        foreach ($turler as $t) {
            EvrakTuru::updateOrCreate(['kod' => $t['kod']], $t);
        }
    }
}
