<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evrak turleri VERI olarak modellenir -- Plan v1.0 md.3 dipnotu.
 * v1'de form ALANLARI kodda statik; evrak turleri ise burada. Bu, Faz 2'deki
 * form olusturucuya zemin hazirlar (o zaman alanlar da veriye tasinir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evrak_turleri', function (Blueprint $t) {
            $t->id();
            $t->string('kod', 50)->unique();          // ticaret_sicil_gazetesi
            $t->string('ad');                          // "Ticaret sicil gazetesi"
            $t->text('aciklama')->nullable();

            // Hangi basvuru turlerinde istenir: ["kurum","basin_mensubu",...]
            $t->jsonb('basvuru_turleri');
            $t->boolean('zorunlu')->default(true);

            $t->jsonb('izinli_formatlar')->nullable(); // ["pdf","jpg","png"]
            $t->unsignedInteger('maks_boyut_kb')->default(8192);

            // Kimlik/pasaport gorseli gibi hassas evrak: at-rest sifreli saklanir,
            // reddedilen/iptal basvurularda imha politikasina girer (md.11 KVKK).
            $t->boolean('hassas')->default(false);
            $t->unsignedSmallInteger('imha_gun')->nullable();

            $t->unsignedSmallInteger('sira')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evrak_turleri');
    }
};
