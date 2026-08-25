<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duyuruya video -- Yusuf revizyonu md.6'nin duyuru ayagi (25.08.2026).
 *
 * Bulten ekleri `ekler` dizisinde tutuluyor ve video oradan geliyordu; duyuruda
 * ise tek `gorsel_yolu` vardi. Videoyu o sutuna doldurmak YANLIS olurdu:
 *   - liste/onizleme gorseli hep `gorsel_yolu`dan okunuyor, video koyunca
 *     onizleme kirilir,
 *   - beyaz liste MIME kontrolu alanin adiyla degil icerigiyle esleseydi bile
 *     "gorsel" alaninin video tutmasi sonraki okuyucuyu yaniltir.
 * Bu yuzden AYRI sutun: duyuruda gorsel de video da ayni anda bulunabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duyurular', function (Blueprint $t) {
            $t->string('video_yolu')->nullable()->after('gorsel_yolu');
        });
    }

    public function down(): void
    {
        Schema::table('duyurular', function (Blueprint $t) {
            $t->dropColumn('video_yolu');
        });
    }
};
