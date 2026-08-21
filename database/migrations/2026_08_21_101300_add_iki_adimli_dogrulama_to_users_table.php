<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iki adimli dogrulama -- Plan v1.0 md.11: yetkili hesaplarinda 2FA ZORUNLU.
 * Filament 5'in yerlesik MFA'si bu iki alani kullanir; ikisi de sifreli saklanir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('iki_adimli_gizli')->nullable()->after('password');
            $t->text('iki_adimli_kurtarma_kodlari')->nullable()->after('iki_adimli_gizli');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['iki_adimli_gizli', 'iki_adimli_kurtarma_kodlari']);
        });
    }
};
