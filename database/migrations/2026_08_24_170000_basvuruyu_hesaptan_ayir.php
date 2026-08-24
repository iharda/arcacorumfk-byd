<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Basvuru akisi v2 -- hesap ONAY aninda acilir (Revizyon md.2.1).
 *
 * Basvuran, onaya kadar sisteme hic girmez; bu yuzden basvurunun bir kullanici
 * kaydina bagli olmasi ZORUNLU olmaktan cikar. Iletisim bilgisi basvurunun
 * kendi ustunde durur: hesap yokken bildirim oraya gider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            // Hesap yokken bildirim ve kuyruk gosterimi bu ucuyle calisir.
            $t->string('basvuran_ad', 120)->nullable()->after('kurum_id');
            $t->string('basvuran_eposta', 150)->nullable()->after('basvuran_ad');
            $t->string('basvuran_telefon', 20)->nullable()->after('basvuran_eposta');

            $t->index('basvuran_eposta');
        });

        /*
         * 🪤 kullanici_id icin ->change() KULLANILMADI: Laravel'in kolon
         * yeniden tanimlamasi foreignId'nin FK ve index'ini de yeniden kurmaya
         * calisir; PostgreSQL'de bu, var olan kisiti dusurup yeniden yaratmak
         * demek. Ham SQL yalnizca NOT NULL kisitini kaldirir, FK'ye dokunmaz.
         */
        DB::statement('ALTER TABLE basvurular ALTER COLUMN kullanici_id DROP NOT NULL');

        // Mevcut basvurularin iletisim bilgisi hesaptan kopyalanir.
        DB::statement('
            UPDATE basvurular b SET
                basvuran_ad = u.name,
                basvuran_eposta = u.email,
                basvuran_telefon = u.telefon
            FROM users u
            WHERE u.id = b.kullanici_id
              AND b.basvuran_eposta IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            // PostgreSQL kolonu dusurunce ustundeki index'i de dusurur.
            $t->dropColumn(['basvuran_ad', 'basvuran_eposta', 'basvuran_telefon']);
        });

        // NOT NULL yalnizca hesapsiz basvuru KALMADIYSA geri konabilir.
        if (DB::table('basvurular')->whereNull('kullanici_id')->doesntExist()) {
            DB::statement('ALTER TABLE basvurular ALTER COLUMN kullanici_id SET NOT NULL');
        }
    }
};
