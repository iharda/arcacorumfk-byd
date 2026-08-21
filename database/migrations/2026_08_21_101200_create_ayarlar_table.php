<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem ayarlari -- Plan v1.0 md.8 "Sistem ayarlari (kurum teyidi ac/kapa vb.)".
 * Anahtar/deger; her degisiklik denetim kaydina dusurulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayarlar', function (Blueprint $t) {
            $t->id();
            $t->string('anahtar', 80)->unique();
            $t->jsonb('deger')->nullable();
            $t->string('grup', 40)->default('genel');
            $t->string('aciklama')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayarlar');
    }
};
