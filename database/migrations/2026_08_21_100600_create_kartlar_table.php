<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uretilen basin kartlari -- Plan v1.0 md.6.
 * Her uretim yeni SURUM; eskisi arsivlenir, silinmez.
 * 🕳️ ValCert dersi: uretilen dosya ile kaydin yolu senkron kalmazsa editor/onizleme
 * bos sayfaya duser. Bu yuzden yol ve surum ayni satirda tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartlar', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->foreignId('akreditasyon_id')->constrained('akreditasyonlar')->cascadeOnDelete();
            $t->unsignedSmallInteger('surum')->default(1);

            $t->string('disk', 30)->default('kart');
            $t->string('pdf_yolu')->nullable();
            $t->string('gorsel_yolu')->nullable();     // panelde onizleme
            $t->unsignedInteger('boyut')->nullable();

            // QR imzasi surumlu: anahtar rotasyonunda eski kartlar dogrulanmaya
            // devam eder (md.6 -- "imza versiyonu QR yukune eklenir").
            $t->unsignedSmallInteger('qr_anahtar_surumu')->default(1);

            $t->boolean('arsiv')->default(false);
            $t->timestamp('uretildi_at')->nullable();
            $t->foreignId('ureten_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['akreditasyon_id', 'surum']);
            $t->index(['akreditasyon_id', 'arsiv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartlar');
    }
};
