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
                'anahtar' => 'bolgeler', 'grup' => 'kapi',
                'deger' => ['saha_kenari' => 'Saha kenarı', 'basin_locasi' => 'Basın locası', 'karma_alan' => 'Karma alan', 'basin_toplanti_salonu' => 'Basın toplantı salonu'],
                'aciklama' => 'Tanımlı bölge yetkileri.',
            ],
        ];

        foreach ($ayarlar as $a) {
            Ayar::updateOrCreate(['anahtar' => $a['anahtar']], $a);
        }
    }
}
