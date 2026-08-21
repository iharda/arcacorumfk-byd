<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Davet linkleri -- Plan v1.0 md.5.2 "Yol B".
 * Kurum calisan basvurusunu BASLATIR, ama kimlik/foto gibi kisisel veriyi
 * calisanin KENDISI yukler. Token yalnizca hash olarak saklanir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('davetler', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->foreignId('kurum_id')->constrained('kurumlar')->cascadeOnDelete();
            $t->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('basvuru_id')->nullable()->constrained('basvurular')->nullOnDelete();

            $t->string('ad_soyad');
            $t->string('eposta');
            $t->string('token_hash', 64)->unique();

            $t->timestamp('gecerlilik_bitis');
            $t->timestamp('kullanildi_at')->nullable();
            $t->timestamp('iptal_at')->nullable();
            $t->unsignedSmallInteger('gonderim_sayisi')->default(1);

            $t->timestamps();
            $t->index(['kurum_id', 'kullanildi_at']);
            $t->index('eposta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('davetler');
    }
};
