<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Basvuru numarasi kart numarasiyla ayni dili konusuyor: KR7R yerine
 * 2026-BV-0137 (Cuneyt Bey, 03.09.2026 ekran kaydi 00:19 -- "buna 'no' derken
 * bu ne? Kart no gibi normal olacak, buna bir duzen koymamiz lazim").
 *
 * 🪤 Onceki bicim (27.08.2026, Yusuf/IT) dort karakterlik RASTGELE koddu;
 * gerekcesi "26 haneli ULID telefonda okunmuyor"du ve o gerekce ayakta.
 * Yeni bicim de kisa ve okunur, ustune SIRALANIYOR -- eskisinin yerini alir,
 * yanina eklenmez. Numaralar YENIDEN URETILIYOR; pilot verisi disinda
 * dagitilmis numara yok.
 *
 * 🔑 Numara artik GONDERIM aninda veriliyor (taslakta degil), bu yuzden sutun
 * NULL kabul ediyor: gonderilmemis basvuru numara YAKMAZ, sirada bosluk olmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            // Akreditasyondaki (yil, tur_kodu, sira) ucusunun karsiligi; tur
            // kodu basvuruda sabit "BV" oldugu icin iki sutun yetiyor.
            $t->unsignedSmallInteger('no_yil')->nullable()->after('basvuru_no');
            $t->unsignedInteger('no_sira')->nullable()->after('no_yil');
        });

        // char(4) NOT NULL -> varchar(16) NULL. Benzersiz kisit yerinde kalir.
        DB::statement('ALTER TABLE basvurular ALTER COLUMN basvuru_no TYPE varchar(16)');
        DB::statement('ALTER TABLE basvurular ALTER COLUMN basvuru_no DROP NOT NULL');

        $sayac = [];

        // 🪤 Sira GONDERIM sirasina gore: numara buyudukce basvuru yenilenir.
        // NULL'lar PostgreSQL'de ASC'de sona duser, sayaci bozmazlar.
        foreach (DB::table('basvurular')->orderBy('gonderildi_at')->orderBy('id')
            ->get(['id', 'gonderildi_at']) as $satir) {

            if ($satir->gonderildi_at === null) {
                DB::table('basvurular')->where('id', $satir->id)
                    ->update(['basvuru_no' => null, 'no_yil' => null, 'no_sira' => null]);

                continue;
            }

            // 🕐 Yil ISTANBUL'a gore: 1 Ocak 01:00'de gonderilen basvuru
            // panelde 2027 tarihi gosterirken 2026 numarasi tasimasin.
            $yil = Carbon::parse($satir->gonderildi_at)->timezone('Europe/Istanbul')->year;
            $sira = $sayac[$yil] = ($sayac[$yil] ?? 0) + 1;

            DB::table('basvurular')->where('id', $satir->id)->update([
                'basvuru_no' => sprintf('%d-BV-%04d', $yil, $sira),
                'no_yil' => $yil,
                'no_sira' => $sira,
            ]);
        }

        Schema::table('basvurular', function (Blueprint $t) {
            // 🔒 Uretici carpismayi kacirirsa insert burada patlar; basvuru_no
            // uzerindeki benzersizlik zaten var, bu ikincisi sayacin kendisini
            // korur (ayni sira iki kez dagitilamaz).
            $t->unique(['no_yil', 'no_sira']);
        });
    }

    public function down(): void
    {
        Schema::table('basvurular', function (Blueprint $t) {
            $t->dropUnique(['no_yil', 'no_sira']);
            $t->dropColumn(['no_yil', 'no_sira']);
        });

        // Eski bicime donus: 4 karakterlik rastgele kod, NOT NULL.
        // 💣 Numaralar geri gelmez; eski kodlar hicbir yerde saklanmiyordu.
        $alfabe = '23456789BCDFGHJKMNPQRSTVWXYZ';
        $kullanilan = [];

        foreach (DB::table('basvurular')->pluck('id') as $id) {
            do {
                $no = '';
                for ($i = 0; $i < 4; $i++) {
                    $no .= $alfabe[random_int(0, strlen($alfabe) - 1)];
                }
            } while (isset($kullanilan[$no]));

            $kullanilan[$no] = true;
            DB::table('basvurular')->where('id', $id)->update(['basvuru_no' => $no]);
        }

        DB::statement('ALTER TABLE basvurular ALTER COLUMN basvuru_no TYPE char(4)');
        DB::statement('ALTER TABLE basvurular ALTER COLUMN basvuru_no SET NOT NULL');
    }
};
