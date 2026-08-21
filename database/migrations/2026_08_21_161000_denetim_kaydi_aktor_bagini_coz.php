<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denetim kaydındaki aktör yabancı anahtarını KALDIRIR.
 *
 * 💥 Neden: `aktor_id` "nullOnDelete" ile bağlıydı. Bir kullanıcı silindiğinde
 * PostgreSQL `UPDATE denetim_kaydi SET aktor_id = NULL` çalıştırıyor; denetim
 * kaydını kilitleyen tetikleyici bunu reddediyor ve SİLME TAMAMEN BAŞARISIZ
 * oluyordu. Yani "kullanıcı silinemez" durumuna düşmüştük — KVKK silme talebi
 * geldiğinde anlaşılmaz bir veritabanı hatasıyla karşılaşırdık.
 *
 * Doğrusu: denetim kaydı ilişkisel veri değil, TARİHSEL KAYITTIR. Kim olduğu
 * zaten `aktor_ad` sütununda metin olarak duruyor (kullanıcı silinse bile
 * kaybolmasın diye baştan böyle tasarlanmıştı). Yabancı anahtar bu tasarımla
 * çelişiyordu; sütun kalıyor, kısıt gidiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('denetim_kaydi', function (Blueprint $t) {
            $t->dropForeign(['aktor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('denetim_kaydi', function (Blueprint $t) {
            $t->foreign('aktor_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
