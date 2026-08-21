<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denetim kaydi -- Plan v1.0 md.10. SADECE EKLENIR.
 * updated_at yok, softDelete yok. Veritabani seviyesinde de kilitlenir:
 * uygulama kullanicisina UPDATE/DELETE verilmez (asagidaki REVOKE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denetim_kaydi', function (Blueprint $t) {
            $t->id();

            $t->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('aktor_tip', 30)->default('kullanici'); // kullanici|sistem|kapi_istemcisi
            $t->string('aktor_ad')->nullable();  // kullanici silinse bile kim oldugu kalir

            $t->string('olay', 60);              // basvuru.onaylandi, akreditasyon.iptal ...
            $t->string('kayit_tipi', 60)->nullable();
            $t->unsignedBigInteger('kayit_id')->nullable();
            $t->string('kayit_etiketi')->nullable();

            $t->jsonb('eski')->nullable();
            $t->jsonb('yeni')->nullable();
            $t->text('not')->nullable();

            $t->string('ip', 45)->nullable();
            $t->string('tarayici')->nullable();

            $t->timestamp('created_at')->nullable();

            $t->index(['kayit_tipi', 'kayit_id']);
            $t->index(['olay', 'created_at']);
            $t->index(['aktor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denetim_kaydi');
    }
};
