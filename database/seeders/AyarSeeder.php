<?php

namespace Database\Seeders;

use App\Models\Ayar;
use Illuminate\Database\Seeder;

class AyarSeeder extends Seeder
{
    public function run(): void
    {
        $ayarlar = [
            [
                'anahtar' => 'kurum_teyidi_istensin', 'deger' => false, 'grup' => 'basvuru',
                'aciklama' => 'Basın mensubu kendisi başvurduğunda kurum ayrıca teyit etsin mi? (Plan v1.0 md.5.2)',
            ],
            [
                'anahtar' => 'davet_gecerlilik_gun', 'deger' => 7, 'grup' => 'basvuru',
                'aciklama' => 'Kurum davet linkinin geçerlilik süresi (gün).',
            ],
            [
                'anahtar' => 'duzeltme_bileti_gun', 'deger' => 14, 'grup' => 'basvuru',
                'aciklama' => 'Eksik evrak istendiginde basvurana giden, hesap gerektirmeyen baglantinin omru (gun).',
            ],
            [
                'anahtar' => 'yeniden_basvuru_bekleme_gun', 'deger' => 0, 'grup' => 'basvuru',
                'aciklama' => 'Reddedilen kisi kac gun sonra yeniden basvurabilir? 0 = bekleme yok. Ayrilanlara uygulanmaz.',
            ],
            [
                'anahtar' => 'kart_yil', 'deger' => null, 'grup' => 'kart',
                'aciklama' => 'Kart numarasındaki yıl. Boşsa içinde bulunulan yıl kullanılır.',
            ],
            [
                'anahtar' => 'mukerrer_okutma_saniye', 'deger' => 30, 'grup' => 'kapi',
                'aciklama' => 'AYNI kapıda bu süre içinde yeniden okutma: görevli uyarılır, geçiş engellenmez. 0 = kapalı.',
            ],
            [
                // 🔑 Yinelenen okumadan AYRI bir şey (Düzeltme listesi md.12):
                // biri aynı kapıdaki tekrarı, bu TÜM kapılardaki kart
                // paylaşımını yakalar. İkisi de geçişi ENGELLEMEZ.
                'anahtar' => 'kart_paylasimi_saniye', 'deger' => 120, 'grup' => 'kapi',
                'aciklama' => 'Aynı kart BAŞKA bir kapıda bu süre içinde okutulduysa görevli uyarılır (yüz kontrolü). 0 = kapalı.',
            ],
            [
                'anahtar' => 'kart_tur_kodlari', 'grup' => 'kart',
                'deger' => ['basin_mensubu' => 'K', 'icerik_ureticisi' => 'B'],
                'aciklama' => 'Kart numarasındaki tür harfi (2026-K-0042). I ve O kullanılmaz: 1 ve 0 ile karışır.',
            ],
            [
                /*
                 * 🔒 Guvenlikte dogru varsayilan KAPALI'dir ama burada BOS
                 * liste "her kapidan gecer" demek (Duzeltme listesi md.9):
                 * kisitli alani olan kulup bu listeyi Ayarlar'dan doldurur.
                 * Bos da olsa kayit VAR olsun -- ayar tablosu sistemdeki
                 * ayarlarin tam listesi olmali.
                 */
                'anahtar' => 'varsayilan_bolgeler', 'deger' => [], 'grup' => 'kapi',
                'aciklama' => 'Yeni akreditasyonlara onay aninda atanan bolgeler. Bos = kart her kapidan gecer.',
            ],
            [
                'anahtar' => 'bolgeler', 'grup' => 'kapi',
                'deger' => ['saha_kenari' => 'Saha kenarı', 'basin_locasi' => 'Basın locası', 'karma_alan' => 'Karma alan', 'basin_toplanti_salonu' => 'Basın toplantı salonu'],
                'aciklama' => 'Tanımlı bölge yetkileri.',
            ],
        ];

        /*
         * 💀 `updateOrCreate` DEGERI de eziyordu: kulup panelden kurum
         * teyidini kapatip elle bir `db:seed` calistirdiginda ayar sessizce
         * varsayilana donuyordu. Tohumlama bir ayarin VAR OLMASINI saglar,
         * degerini SAHIPLENMEZ -- deger Ayarlar ekranindan yonetiliyor.
         * Grup ve aciklama guncellenmeye devam ediyor: onlar kodun bilgisi.
         */
        foreach ($ayarlar as $a) {
            $ayar = Ayar::firstOrCreate(['anahtar' => $a['anahtar']], $a);

            $ayar->fill(['grup' => $a['grup'], 'aciklama' => $a['aciklama']])->save();
        }
    }
}
