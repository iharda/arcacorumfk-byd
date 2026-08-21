<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Medya merkezi icerikleri -- Plan v1.0 md.8: duyuru, antrenman takvimi, bulten. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duyurular', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->string('baslik');
            $t->text('ozet')->nullable();
            $t->longText('icerik')->nullable();
            $t->string('gorsel_yolu')->nullable();
            $t->boolean('yayinda')->default(false);
            $t->timestamp('yayin_at')->nullable();
            $t->boolean('bildirim_gonderildi')->default(false);
            $t->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['yayinda', 'yayin_at']);
        });

        Schema::create('antrenmanlar', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->string('baslik')->nullable();
            $t->timestamp('baslangic_at');
            $t->timestamp('bitis_at')->nullable();
            $t->string('yer')->nullable();
            // Basina acik mi, yoksa yalnizca ilk 15 dk mi vb.
            $t->boolean('basina_acik')->default(true);
            $t->text('not')->nullable();
            $t->boolean('yayinda')->default(false);
            $t->boolean('bildirim_gonderildi')->default(false);
            $t->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['yayinda', 'baslangic_at']);
        });

        Schema::create('bultenler', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->string('baslik');
            $t->longText('icerik')->nullable();
            // Bulten ekleri: [{ad, disk, yol, boyut, mime}]
            $t->jsonb('ekler')->nullable();
            $t->boolean('yayinda')->default(false);
            $t->timestamp('yayin_at')->nullable();
            $t->boolean('bildirim_gonderildi')->default(false);
            $t->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['yayinda', 'yayin_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bultenler');
        Schema::dropIfExists('antrenmanlar');
        Schema::dropIfExists('duyurular');
    }
};
