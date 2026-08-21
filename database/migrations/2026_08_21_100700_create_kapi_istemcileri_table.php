<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnike / gise istemcileri -- Plan v1.0 md.2 ve md.11.
 * Her istemci: AYRI API anahtari + IP beyaz listesi.
 * Anahtar YALNIZCA hash olarak durur; uretim aninda bir kez gosterilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kapi_istemcileri', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->string('ad');                          // "Kuzey turnike 1"
            $t->string('kapi_kodu', 30);               // gecis kaydina yazilir
            $t->string('anahtar_onek', 12)->unique();  // panelde gosterilen kisim
            $t->string('anahtar_hash');                // hash('sha256', anahtar)

            $t->jsonb('ip_listesi')->nullable();       // ["1.2.3.4","1.2.3.0/24"]
            // Bu kapidan gecebilecek bolgeler; bos = bolge kontrolu yapilmaz
            $t->jsonb('bolgeler')->nullable();

            $t->boolean('aktif')->default(true);
            $t->timestamp('son_kullanim_at')->nullable();
            $t->string('son_kullanim_ip', 45)->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index('aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kapi_istemcileri');
    }
};
