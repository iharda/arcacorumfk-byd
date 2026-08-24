<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calisan sayisi serbest rakam yerine ARALIK secimi -- Revizyon md.2.3.
 *
 * Eski `calisan_sayisi` sutunu SILINMEZ: gecmis veri korunur ve rapor gerekirse
 * ham sayi elde kalir. Yeni kayitlarda yalnizca aralik doldurulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kurumlar', function (Blueprint $t) {
            $t->string('calisan_araligi', 10)->nullable()->after('calisan_sayisi');
        });

        // App\Enums\CalisanAraligi ile ayni esikler.
        DB::statement("
            UPDATE kurumlar SET calisan_araligi = CASE
                WHEN calisan_sayisi IS NULL THEN NULL
                WHEN calisan_sayisi <= 5  THEN '1-5'
                WHEN calisan_sayisi <= 10 THEN '6-10'
                WHEN calisan_sayisi <= 20 THEN '11-20'
                WHEN calisan_sayisi <= 50 THEN '21-50'
                ELSE '50+'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('kurumlar', function (Blueprint $t) {
            $t->dropColumn('calisan_araligi');
        });
    }
};
