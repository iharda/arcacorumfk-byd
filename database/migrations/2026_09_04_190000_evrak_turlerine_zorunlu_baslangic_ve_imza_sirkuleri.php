<?php

use App\Enums\BasvuruTuru;
use App\Models\EvrakTuru;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İmza sirküleri (M7) + zorunluluğun YÜRÜRLÜK TARİHİ.
 *
 * 💀 `zorunlu => true` yazmak YOLDAKİ başvuruları da vurur. Düzeltme turundan
 * dönen eski bir başvuru `gonder()` çağrısında
 * "Eksik zorunlu evrak: İmza sirküleri" ile durur ve başvuran o belgeyi
 * YÜKLEYEMEZ -- düzeltme bileti yalnız yetkilinin işaretlediği alanları açar.
 * Çıkmaz sokak. Şu anda kuyrukta 13 kurumsal başvuru var.
 *
 * Çözüm: `zorunlu_baslangic`. Zorunluluk yalnızca bu tarihten SONRA oluşturulan
 * başvurular için işler; kuyruktakiler eski kuralla tamamlanır. Sütun NULL ise
 * kural her zaman geçerlidir (mevcut türlerin hepsi böyle -- davranış değişmez).
 *
 * Evrak türü `updateOrCreate` ile ekleniyor: `dagit.sh` `migrate` çalıştırır,
 * `db:seed` çalıştırmaz. Tekrar çalışsa da kopya üretmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evrak_turleri', function (Blueprint $t) {
            $t->date('zorunlu_baslangic')->nullable()->after('zorunlu');
        });

        EvrakTuru::updateOrCreate(
            ['kod' => 'imza_sirkuleri'],
            [
                'ad' => 'İmza sirküleri',
                'aciklama' => 'Yetkiliyi temsile yetkili kıldığını gösteren, '
                    .'geçerli imza sirküleri veya imza beyannamesi',
                'basvuru_turleri' => [BasvuruTuru::Kurum->value],
                'zorunlu' => true,
                /*
                 * 🔑 YARIN. "Bugün" yazmak yetmez: bugün açılmış başvurular da
                 * kuralın içinde kalır ve gönderim yapamaz hâle gelirler
                 * (04.09.2026'da 14 kurum kaydı açılmıştı). Yarından itibaren
                 * demek, ŞU AN var olan her başvuruyu muaf tutmak demek.
                 */
                'zorunlu_baslangic' => now()->addDay()->toDateString(),
                'izinli_formatlar' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                'maks_boyut_kb' => 8192,
                'hassas' => false,
                'imha_gun' => null,
                'sira' => 15,
                'aktif' => true,
            ],
        );
    }

    public function down(): void
    {
        EvrakTuru::where('kod', 'imza_sirkuleri')->delete();

        Schema::table('evrak_turleri', function (Blueprint $t) {
            $t->dropColumn('zorunlu_baslangic');
        });
    }
};
