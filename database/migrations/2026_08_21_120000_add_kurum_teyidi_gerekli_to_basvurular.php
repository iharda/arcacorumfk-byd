<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurum teyidi -- Plan v1.0 md.5.2.
 *
 * Teyit İSTENİP istenmediği başvuru GÖNDERİLDİĞİ AN dondurulur. Ayar sonradan
 * değiştirilirse yolda olan başvuruların kuralı değişmesin: bir başvuru hangi
 * kuralla gönderildiyse o kuralla sonuçlanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            $t->boolean('kurum_teyidi_gerekli')->default(false)->after('kurum_baslatti');
            // Teyit bekleyen başvuru yetkili kuyruğuna DÜŞMEZ; bu sorgu sık.
            $t->index(['kurum_id', 'kurum_teyidi_gerekli', 'kurum_teyidi'], 'basvurular_teyit_idx');
        });
    }

    public function down(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            $t->dropIndex('basvurular_teyit_idx');
            $t->dropColumn('kurum_teyidi_gerekli');
        });
    }
};
