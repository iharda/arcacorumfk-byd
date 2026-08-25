<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duzeltme turlari -- Yusuf revizyonu 25.08.2026.
 *
 * 💀 Eskiden duzeltme talebi `basvurular.duzeltme_notlari` icinde TEK bir
 * jsonb alanda duruyordu ve her turda UZERINE YAZILIYORDU. Sonuc:
 *   - "ilk bilgiler / duzeltme 01 / duzeltme 02" gecmisi YOKTU
 *   - alanin ONCEKI degeri hicbir yerde tutulmuyordu
 *   - basvuran neyi yeni ekledigini, yetkili neyin degistigini goremiyordu
 *
 * Her tur BURADA ayri bir satirdir: ne istendi, ne zaman istendi, basvuran ne
 * cevapladi, hangi alan neyden neye dondu.
 *
 * `basvurular.duzeltme_notlari` KALDI: acik turun notlarini tasiyor ve
 * "basvuran yalnizca isaretli alani duzeltebilir" kontrolu ona bakiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basvuru_duzeltmeleri', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->foreignId('basvuru_id')->constrained('basvurular')->cascadeOnDelete();

            // 1'den baslar; ekranda "Duzeltme talebi 01" diye gorunur.
            $t->unsignedSmallInteger('sira');

            // İSTEK: {anahtar: aciklama} -- anahtarlar DuzeltmeAlanlari semasi.
            $t->jsonb('talep_notlari');
            // Alan listemizde OLMAYAN ek talepler (Yusuf md.3):
            // [{anahtar, etiket, tip: 'dosya'|'metin', aciklama}]
            $t->jsonb('ek_talepler')->nullable();
            $t->text('talep_gerekcesi')->nullable();

            $t->foreignId('talep_eden_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('talep_at');

            // YANIT
            $t->text('yanit_aciklama')->nullable();
            $t->timestamp('yanit_at')->nullable();
            // {anahtar: {eski: ..., yeni: ...}} -- onceki/sonraki deger burada.
            $t->jsonb('degisiklikler')->nullable();

            $t->timestamps();

            $t->unique(['basvuru_id', 'sira']);
            $t->index(['basvuru_id', 'talep_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basvuru_duzeltmeleri');
    }
};
