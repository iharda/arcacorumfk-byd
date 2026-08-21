<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medya kurumlari -- Plan v1.0 md.3.1.
 * Kurum AKREDITE olmadan calisani basvuramaz (on kosul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurumlar', function (Blueprint $t) {
            $t->id();
            // Disariya acilan kimlik. Sayisal id URL'de HIC gorunmez (IDOR).
            $t->ulid('ulid')->unique();

            $t->string('resmi_unvan');
            $t->string('adres')->nullable();
            $t->string('il')->nullable();
            $t->string('ilce')->nullable();
            $t->string('telefon', 20)->nullable();      // +90 XXX XXX XX XX
            $t->string('eposta')->nullable();

            $t->string('vergi_dairesi')->nullable();
            $t->string('vergi_no', 20)->nullable();
            $t->unsignedInteger('calisan_sayisi')->nullable();

            // [{platform, ad, url}] -- yayin platformlari ve sosyal medya ayri tutulur
            $t->jsonb('yayin_platformlari')->nullable();
            $t->jsonb('sosyal_medya')->nullable();

            $t->string('akreditasyon_durumu', 20)->default('beklemede'); // beklemede|akredite|iptal
            // Kulubun kuruma tanidigi kart kontenjani. null = sinirsiz.
            $t->unsignedSmallInteger('kontenjan')->nullable();

            // Kurum, calisan basvurularini ayrica teyit etsin mi? null = sistem
            // ayari gecerli (Plan v1.0 md.5.2 -- opsiyonel ayar).
            $t->boolean('teyit_istensin')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index('akreditasyon_durumu');
            $t->index('vergi_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurumlar');
    }
};
