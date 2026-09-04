<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Geçmiş veri düzeltmesi -- M1-A.
 *
 * `BasvuruAkisi::reddet()` / `iptalEt()` bugüne kadar kuruma hiç dokunmuyordu:
 * kurumsal başvurusu reddedilen ya da iptal edilen kurum, `kurumlar` tablosunda
 * sonsuza kadar "Beklemede" kalıyordu. Akış artık kararı kuruma da yazıyor;
 * bu migration önceden karara bağlanmış kayıtları aynı hâle getirir.
 *
 * ⚠️ DAR KAPSAM. Yalnızca şu satırlar taşınır:
 *   - kurumun durumu HÂLÂ `beklemede` (akredite ya da iptal olana dokunulmaz),
 *   - kurumun EN SON kurumsal başvurusu reddedilmiş/iptal edilmiş.
 * Silinmiş başvurular (`deleted_at`) karar sayılmaz -- geri alınabilirler.
 *
 * Tersi (`down`) kararı `beklemede`ye geri alır; veri kaybı yok, çünkü asıl
 * bilgi `basvurular.durum` sütununda duruyor ve buradaki değer ondan türetiliyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sayac = DB::update(<<<'SQL'
            UPDATE kurumlar k
            SET akreditasyon_durumu = CASE son.durum
                    WHEN 'reddedildi' THEN 'reddedildi'
                    ELSE 'iptal_edildi'
                END,
                updated_at = now()
            FROM (
                SELECT DISTINCT ON (kurum_id) kurum_id, durum
                FROM basvurular
                WHERE tur = 'kurum' AND kurum_id IS NOT NULL AND deleted_at IS NULL
                ORDER BY kurum_id, id DESC
            ) son
            WHERE son.kurum_id = k.id
              AND k.akreditasyon_durumu = 'beklemede'
              AND son.durum IN ('reddedildi', 'iptal_edildi')
        SQL);

        if ($sayac > 0) {
            echo "  → {$sayac} kurumun durumu başvuru kararıyla eşitlendi.".PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::update("UPDATE kurumlar SET akreditasyon_durumu = 'beklemede'
                    WHERE akreditasyon_durumu IN ('reddedildi', 'iptal_edildi')");
    }
};
