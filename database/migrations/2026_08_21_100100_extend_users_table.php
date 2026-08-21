<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kullanicilar -- Plan v1.0 md.5.5.
 * Hesap BASVURU ANINDA acilir; onaya kadar panel yalnizca durum + evrak gosterir.
 * Sistem sifre uretmez, kullanici aktivasyon linkiyle kendi belirler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->ulid('ulid')->nullable()->unique()->after('id');
            $t->foreignId('kurum_id')->nullable()->after('ulid')
                ->constrained('kurumlar')->nullOnDelete();

            $t->string('telefon', 20)->nullable()->after('email');
            $t->string('adres')->nullable()->after('telefon');
            $t->string('il')->nullable()->after('adres');
            $t->string('ilce')->nullable()->after('il');

            // Kurum calisani ayrildiginda: akreditasyon otomatik iptal (md.5.4)
            $t->timestamp('ayrildi_at')->nullable()->after('ilce');
            $t->boolean('aktif')->default(true)->after('ayrildi_at');
            $t->timestamp('son_giris_at')->nullable()->after('aktif');

            $t->softDeletes();

            $t->index('kurum_id');
            $t->index('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('kurum_id');
            $t->dropColumn(['ulid', 'telefon', 'adres', 'il', 'ilce', 'ayrildi_at', 'aktif', 'son_giris_at', 'deleted_at']);
        });
    }
};
