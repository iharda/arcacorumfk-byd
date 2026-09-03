<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * webp'i evrak türlerinin izinli biçimlerine ekler -- saha notları S6.
 *
 * 🪤 config/bys.php'deki `mime_izin` listesinde `image/webp` SERBESTTİ ama
 * hiçbir evrak türü webp kabul etmiyordu: ölü izin. Telefondan paylaşılan
 * görseller giderek webp oluyor; kullanıcı yükleyemediğinde sebebini
 * anlamıyordu. Sessizce reddedilen yükleme en can sıkıcı hata.
 *
 * Seeder da güncellendi; bu geçiş ÜRETİMDEKİ mevcut satırlar için.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('evrak_turleri')->get(['id', 'izinli_formatlar']) as $tur) {
            $bicimler = json_decode($tur->izinli_formatlar ?: '[]', true) ?: [];

            if (in_array('webp', $bicimler, true)) {
                continue;
            }

            $bicimler[] = 'webp';

            DB::table('evrak_turleri')->where('id', $tur->id)
                ->update(['izinli_formatlar' => json_encode(array_values($bicimler))]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('evrak_turleri')->get(['id', 'izinli_formatlar']) as $tur) {
            $bicimler = json_decode($tur->izinli_formatlar ?: '[]', true) ?: [];

            DB::table('evrak_turleri')->where('id', $tur->id)
                ->update(['izinli_formatlar' => json_encode(
                    array_values(array_filter($bicimler, fn ($b) => $b !== 'webp'))
                )]);
        }
    }
};
