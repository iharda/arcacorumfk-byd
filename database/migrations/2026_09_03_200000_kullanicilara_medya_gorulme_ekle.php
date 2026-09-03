<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medya merkezine son bakis ani. "Yeni" rozeti buna gore cikar.
 *
 * 🔑 Ayri bir OKUMA TABLOSU kurulmadi: sorulan soru "bu kaydi okudum mu"
 * degil, "son bakisimdan sonra ne yayinlandi" -- zaman damgasi yeter. Kayit
 * basina satir tutmak, her liste acilisinda onlarca satir yazmak demekti.
 *
 * ⚠️ Tasarim plani TEK kolon oneriyordu; iki kolon yazildi. Gerekce: plan
 * duyurular ve bultenleri TEK sayfada sekmelere aliyordu, tek damga orada
 * dogruydu. Bugun iki AYRI sayfa var (menude de ayri) ve tek damgayla
 * duyurulari acmak bultenlerin "Yeni" rozetini de dusururdu -- kullanici
 * bultenler listesini hic gormeden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('duyuru_gorulme_at')->nullable()->after('son_giris_at');
            $t->timestamp('bulten_gorulme_at')->nullable()->after('duyuru_gorulme_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['duyuru_gorulme_at', 'bulten_gorulme_at']);
        });
    }
};
