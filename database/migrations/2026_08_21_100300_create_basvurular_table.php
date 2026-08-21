<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Basvurular -- Plan v1.0 md.3 ve md.4. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basvurular', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();

            $t->string('tur', 30);                     // App\Enums\BasvuruTuru
            $t->string('durum', 20)->default('taslak'); // App\Enums\BasvuruDurumu

            $t->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('kurum_id')->nullable()->constrained('kurumlar')->nullOnDelete();

            // v1'de form alanlari statik kodlanir ama DEGERLER burada durur; boylece
            // Faz 2'de form olusturucu geldiginde tablo degistirmeye gerek kalmaz.
            $t->jsonb('form_verisi')->nullable();

            // Alan bazli eksik evrak talebi (md.4): {"alan_adi": "aciklama", ...}
            // Basvuran YALNIZCA burada isaretli alanlari guncelleyebilir.
            $t->jsonb('duzeltme_notlari')->nullable();

            // Yol B: kurum baslatti mi? (md.5.2)
            $t->boolean('kurum_baslatti')->default(false);
            // Kurum teyidi: null = istenmiyor, true/false = kurumun cevabi
            $t->boolean('kurum_teyidi')->nullable();
            $t->timestamp('kurum_teyidi_at')->nullable();

            $t->timestamp('gonderildi_at')->nullable();
            $t->timestamp('incelemeye_alindi_at')->nullable();
            $t->foreignId('inceleyen_id')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('karar_at')->nullable();
            $t->foreignId('karar_veren_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('karar_gerekcesi')->nullable();   // red gerekcesi de burada

            $t->timestamps();
            $t->softDeletes();

            // Yetkili kuyrugu bu ikiliyle filtrelenir -- en sik sorgu.
            $t->index(['durum', 'tur']);
            $t->index(['kurum_id', 'durum']);
            $t->index('kullanici_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basvurular');
    }
};
