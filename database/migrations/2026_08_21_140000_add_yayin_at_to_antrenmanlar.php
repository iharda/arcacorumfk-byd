<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrenman kaydına yayın zamanı.
 *
 * Duyuru ve bültende vardı, takvimde yoktu; ortak yayın servisi üç içeriği de
 * aynı şekilde işlediği için takvim yayına alınamıyordu. Alan aynı zamanda
 * "ne zaman duyuruldu" sorusunun cevabı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antrenmanlar', function (Blueprint $t) {
            $t->timestamp('yayin_at')->nullable()->after('yayinda');
        });
    }

    public function down(): void
    {
        Schema::table('antrenmanlar', function (Blueprint $t) {
            $t->dropColumn('yayin_at');
        });
    }
};
