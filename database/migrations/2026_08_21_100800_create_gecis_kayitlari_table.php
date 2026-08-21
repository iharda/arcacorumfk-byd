<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gecis kayitlari -- Plan v1.0 md.6 ve md.10.
 * BASARISIZ okutmalar da yazilir (imza gecersiz, bulunamadi): saldiri tespiti
 * icin en degerli kayit bunlar. Mac gunu ~30.000 satir hedefleniyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gecis_kayitlari', function (Blueprint $t) {
            $t->id();

            // Imza gecersizse / kayit bulunamadiysa NULL kalir -- yine de loglanir.
            $t->foreignId('akreditasyon_id')->nullable()->constrained('akreditasyonlar')->nullOnDelete();
            $t->foreignId('kapi_istemcisi_id')->nullable()->constrained('kapi_istemcileri')->nullOnDelete();

            $t->string('kapi_kodu', 30)->nullable();
            $t->string('yon', 10)->default('giris');   // giris|cikis
            $t->string('sonuc', 30);                   // App\Enums\GecisSonucu
            $t->string('bolge', 30)->nullable();
            $t->string('sebep')->nullable();

            // Ham QR yuku DEGIL, yalnizca referansi: kisisel veri log'a dusmesin
            $t->string('okunan_referans', 40)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('okundu_at');

            // Yalnizca eklenir; updated_at yok (append-only)
            $t->timestamp('created_at')->nullable();

            $t->index(['okundu_at']);
            $t->index(['akreditasyon_id', 'okundu_at']);
            $t->index(['sonuc', 'okundu_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gecis_kayitlari');
    }
};
