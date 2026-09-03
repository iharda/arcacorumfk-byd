<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Basvuruya 4 karakterlik, insan icin okunabilir numara (Yusuf/IT, 2026-08-27).
 * ULID kalir; bu yalnizca disariya gosterilen etikettir.
 */
return new class extends Migration
{
    /** App\Servisler\BasvuruNoUretici::ALFABE ile AYNI. Goc kendi kendine yetsin. */
    private const ALFABE = '23456789BCDFGHJKMNPQRSTVWXYZ';

    public function up(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            $t->char('basvuru_no', 4)->nullable()->after('ulid');
        });

        // Mevcut kayitlar: her birine benzersiz bir numara. Az sayida satir var,
        // tek tek gitmek yeterli; carpisirsa yeniden dener.
        $kullanilan = [];

        foreach (DB::table('basvurular')->pluck('id') as $id) {
            do {
                $no = $this->rastgele();
            } while (isset($kullanilan[$no]));

            $kullanilan[$no] = true;
            DB::table('basvurular')->where('id', $id)->update(['basvuru_no' => $no]);
        }

        // Once doldur, SONRA zorunlu kil: bos satir kalmadigi kesin.
        DB::statement('ALTER TABLE basvurular ALTER COLUMN basvuru_no SET NOT NULL');

        Schema::table('basvurular', function (Blueprint $t) {
            // 🔒 Son guvence: uretici carpismayi kacirirsa insert burada patlar,
            // sessizce ayni numarayi tasiyan iki basvuru olusmaz.
            $t->unique('basvuru_no');
        });
    }

    public function down(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            $t->dropUnique(['basvuru_no']);
            $t->dropColumn('basvuru_no');
        });
    }

    private function rastgele(): string
    {
        $son = strlen(self::ALFABE) - 1;
        $no = '';

        for ($i = 0; $i < 4; $i++) {
            $no .= self::ALFABE[random_int(0, $son)];
        }

        return $no;
    }
};
