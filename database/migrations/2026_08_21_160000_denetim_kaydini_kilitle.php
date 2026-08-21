<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denetim kaydını VERİTABANI SEVİYESİNDE kilitler -- Plan v1.0 md.10:
 * "Silinemez, güncellenemez."
 *
 * Model üzerinde de engel var ama o yalnızca uygulamadan geçen yolu kapatır.
 * Doğrudan SQL, bir konsol komutu ya da ileride yazılacak bir kod modeli
 * atlarsa kayıt yine değişebilirdi. Tetikleyici hiçbir yolu açık bırakmaz.
 *
 * ⚠️ REVOKE işe yaramaz: tablonun sahibi uygulama kullanıcısının kendisi ve
 * sahip yetkileri geri alınamaz. Bu yüzden tetikleyici.
 * ⚠️ Yalnızca PostgreSQL. MySQL'e taşınırsa karşılığı BEFORE UPDATE/DELETE
 * trigger + SIGNAL SQLSTATE olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->postgresMi()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION byd_denetim_kaydi_kilit() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Denetim kaydi degistirilemez veya silinemez (BYD)';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS byd_denetim_kaydi_kilit_trg ON denetim_kaydi;

            CREATE TRIGGER byd_denetim_kaydi_kilit_trg
                BEFORE UPDATE OR DELETE OR TRUNCATE ON denetim_kaydi
                FOR EACH STATEMENT EXECUTE FUNCTION byd_denetim_kaydi_kilit();
        SQL);
    }

    public function down(): void
    {
        if (! $this->postgresMi()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS byd_denetim_kaydi_kilit_trg ON denetim_kaydi;');
        DB::unprepared('DROP FUNCTION IF EXISTS byd_denetim_kaydi_kilit();');
    }

    private function postgresMi(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
