<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yuklenen evraklar -- Plan v1.0 md.11.
 * 🔒 Public URL YOK: dosyalar kapali kovada, erisim yalnizca kisa omurlu
 * imzali baglantiyla. Yol web root DISINDA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evraklar', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->foreignId('basvuru_id')->constrained('basvurular')->cascadeOnDelete();
            $t->foreignId('evrak_turu_id')->constrained('evrak_turleri')->restrictOnDelete();

            $t->string('disk', 30)->default('evrak');  // .env ile degisir (yerel <-> R2)
            $t->string('yol');
            $t->string('orijinal_ad');
            $t->string('mime', 100);
            $t->unsignedBigInteger('boyut');
            // Ayni dosyanin tekrar yuklenmesini ve bozulmayi yakalar
            $t->string('sha256', 64)->nullable();
            // Magic byte dogrulamasi gecti mi (uzantiya guvenilmez)
            $t->boolean('icerik_dogrulandi')->default(false);
            $t->boolean('sifreli')->default(false);

            $t->string('dogrulama_durumu', 20)->default('bekliyor'); // bekliyor|kabul|ret
            $t->text('dogrulama_notu')->nullable();

            $t->date('imha_tarihi')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['basvuru_id', 'evrak_turu_id']);
            $t->index('imha_tarihi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evraklar');
    }
};
