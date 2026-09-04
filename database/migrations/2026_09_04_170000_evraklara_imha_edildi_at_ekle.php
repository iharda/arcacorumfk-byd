<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evrak imhasını GERÇEKTEN mümkün kılar -- Tutarsızlık incelemesi M2.2.
 *
 * 💀 `evraklar.yol` NOT NULL'dı ama `bys:evrak-imha` tam olarak oraya NULL
 * yazmaya çalışıyor:
 *
 *     $evrak->forceFill(['yol' => null, ...])->saveQuietly();
 *
 * Yani komut ilk uygun evrakta QueryException ile ÇÖKÜYORDU. `chunkById`
 * döngüsünde yakalayan yok; tek bir hata bütün gece koşusunu düşürüyor.
 * Sonuç, incelemenin öngördüğü 500 DEĞİL, daha sessiz ve daha ağırı:
 * saklama süresi dolan kimlik belgeleri hiç imha edilmiyor (KVKK md.11).
 * Henüz kimse fark etmemişti, çünkü `imha_gun = 180` ve ilk evraklar
 * 21.08.2026'da yüklendi -- ilk tetiklenme Şubat 2027.
 *
 * İki değişiklik, ikisi de yalnızca ŞEMA; hiçbir satır değişmez:
 *   1) `yol` NULL kabul etsin  -> imha edebilelim,
 *   2) `imha_edildi_at` eklensin -> ekranda "ne zaman imha edildi" yazabilelim.
 *      (`imha_tarihi` "ne zaman imha EDİLECEK" demek ve imha anında NULL'lanır.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Postgres'te anlık, tablo yeniden yazılmaz.
        DB::statement('ALTER TABLE evraklar ALTER COLUMN yol DROP NOT NULL');

        Schema::table('evraklar', function (Blueprint $t) {
            $t->timestamp('imha_edildi_at')->nullable()->after('imha_tarihi');
        });
    }

    public function down(): void
    {
        Schema::table('evraklar', function (Blueprint $t) {
            $t->dropColumn('imha_edildi_at');
        });

        /*
         * NOT NULL yalnızca geri konabiliyorsa konur. İmha edilmiş evrak varsa
         * kısıtı geri koymak o satırları SİLMEYİ gerektirirdi -- geri alma
         * adımı veri silmez; kısıt açık kalır.
         */
        if (DB::table('evraklar')->whereNull('yol')->doesntExist()) {
            DB::statement('ALTER TABLE evraklar ALTER COLUMN yol SET NOT NULL');
        }
    }
};
