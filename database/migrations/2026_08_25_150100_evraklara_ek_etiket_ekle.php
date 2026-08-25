<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ek talep evraki -- Yusuf revizyonu 25.08.2026.
 *
 * Yetkili alan listemizde OLMAYAN bir belge isteyebiliyor ("yayin sozlesmesi").
 * Bunlarin hepsi tek bir `ek_belge` evrak turune yazilir; hangisinin hangi
 * talep oldugunu `ek_etiket` ayirir.
 *
 * 💣 Bu sutun OLMADAN ikinci ek belge birincisini ARSIVLERDI: EvrakYukleyici
 * ayni turden onceki evraki siliyor ve iki ek talep ayni turu paylasiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evraklar', function (Blueprint $t) {
            $t->string('ek_etiket', 120)->nullable()->after('evrak_turu_id');
        });
    }

    public function down(): void
    {
        Schema::table('evraklar', function (Blueprint $t) {
            $t->dropColumn('ek_etiket');
        });
    }
};
