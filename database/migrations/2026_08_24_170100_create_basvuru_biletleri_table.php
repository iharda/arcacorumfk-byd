<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eksik evrak duzeltme bileti -- Revizyon md.2.2.
 *
 * Yetkili "eksik evrak" dedigi anda basvurana gecici bir baglanti gider; kisi
 * hesaba, sifreye, panele gerek kalmadan yalnizca isaretli alanlari duzeltir.
 * `davetler` tablosunun kardesi: token yalnizca sha256 hash'i olarak saklanir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basvuru_biletleri', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->foreignId('basvuru_id')->constrained('basvurular')->cascadeOnDelete();
            $t->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();

            // 🔒 Ham token SAKLANMAZ; e-postada bir kez gecer.
            $t->string('token_hash', 64)->unique();

            // Ileride 'itiraz', 'belge_yenileme' gibi baska amaclar eklenebilir.
            $t->string('amac', 20)->default('eksik_evrak');

            $t->timestamp('gecerlilik_bitis');
            $t->timestamp('kullanildi_at')->nullable();
            $t->timestamp('iptal_at')->nullable();
            $t->unsignedSmallInteger('gonderim_sayisi')->default(1);

            $t->timestamps();

            // "Bu basvurunun acik bileti var mi" en sik sorgu.
            $t->index(['basvuru_id', 'kullanildi_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basvuru_biletleri');
    }
};
