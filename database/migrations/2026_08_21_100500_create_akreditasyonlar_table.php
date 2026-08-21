<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akreditasyon -- sistemin kalbi. Turnike HER OKUTMADA buraya bakar.
 * Plan v1.0 md.4, md.6, md.7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akreditasyonlar', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();                // QR yukundeki referans

            $t->string('kart_no', 20)->unique();       // 2026-K-0042
            $t->unsignedSmallInteger('yil');
            $t->string('tur_kodu', 2);                 // K | I  (md.6 -- kesinlesecek)
            $t->unsignedInteger('sira');

            $t->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('basvuru_id')->constrained('basvurular')->restrictOnDelete();
            $t->foreignId('kurum_id')->nullable()->constrained('kurumlar')->nullOnDelete();

            $t->string('durum', 20)->default('aktif'); // App\Enums\AkreditasyonDurumu

            // Bolge yetkisi: ["saha_kenari","basin_locasi","karma_alan"]
            // ⚠️ Plan v1.0'da yoktu, 2026-08-20 incelemesinde eklendi.
            $t->jsonb('bolge_yetkileri')->nullable();

            // Sezon/sure yonetimi Faz 2 -- alanlar v1'de HAZIR, bos birakilir (md.4)
            $t->date('gecerlilik_baslangic')->nullable();
            $t->date('gecerlilik_bitis')->nullable();
            $t->string('sezon', 20)->nullable();       // "2026/2027"

            $t->timestamp('askiya_alindi_at')->nullable();
            $t->timestamp('iptal_at')->nullable();
            $t->string('iptal_nedeni')->nullable();
            $t->foreignId('durum_degistiren_id')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamps();

            // Turnike sorgusu: ulid unique index'i uzerinden TEK indeksli erisim.
            $t->index(['durum', 'gecerlilik_bitis']);
            $t->index('kullanici_id');
            $t->index('kurum_id');
            $t->unique(['yil', 'tur_kodu', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akreditasyonlar');
    }
};
