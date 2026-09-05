<?php

use App\Enums\DuzeltmeTuru;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belge talebi turu -- Cuneyt Bey revizyonu (05.09.2026).
 *
 * `tur`: mevcut butun satirlar 'duzeltme'. Varsayilan da o, cunku eski kayit
 * karar oncesi acilmis turdur; geriye donuk hicbir satir anlam degistirmez.
 *
 * `son_tarih`: talebin cevaplanmasi beklenen gun. YAPTIRIM YOK -- sure dolunca
 * sistem hicbir seyi kapatmaz, kayit yoneticinin onune "suresi gecti" diye
 * duser ve karari o verir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basvuru_duzeltmeleri', function (Blueprint $t) {
            $t->string('tur', 20)->default(DuzeltmeTuru::Duzeltme->value)->after('sira');
            $t->date('son_tarih')->nullable()->after('talep_at');

            // Panodaki "suresi gecti" sorgusu: acik turlar tur ve tarihe gore.
            $t->index(['tur', 'yanit_at']);
        });
    }

    public function down(): void
    {
        Schema::table('basvuru_duzeltmeleri', function (Blueprint $t) {
            $t->dropIndex(['tur', 'yanit_at']);
            $t->dropColumn(['tur', 'son_tarih']);
        });
    }
};
