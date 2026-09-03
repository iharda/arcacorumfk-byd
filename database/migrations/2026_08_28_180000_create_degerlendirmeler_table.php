<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yetkili değerlendirmesi (1-5) -- Geliştirme briefi 28.08.2026, Bölüm A.
 *
 * Bir hedefin (kurum ya da kişi) TEK bir güncel puanı olur; geçmiş denetim
 * kaydında durur.
 *
 * 🔑 Neden `kurumlar.puan` sütunu DEĞİL:
 *   - Kişi hedefinin hesabı YOKTUR. Hesap onay anında açılır (Revizyon md.3.2),
 *     yetkili ise puanı inceleme sırasında -- hesap doğmadan -- verir.
 *     `users.puan` yazılacak satırı bulamazdı.
 *   - Puanı yazan ve zamanı da tutulacak; iki modelde tekrarlanan dört sütun
 *     yerine tek tablo.
 *   - İleride "puan geçmişi ekranda dursun" istenirse benzersiz indeks kalkar,
 *     aynı tablo çok satırlıya döner.
 *
 * 🔒 Kısıtlar VERİTABANINDA: ekranı atlayan bir yol (tinker, seeder, ileride
 * bir API) ölçek dışı ya da hedefsiz puan yazamasın. Bir kez bozulan veri,
 * beş ekranda ayrı ayrı savunma kodu yazdırır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degerlendirmeler', function (Blueprint $t) {
            $t->id();

            $t->string('hedef_tip', 10);                 // 'kurum' | 'kisi'

            /*
             * cascade vs null: kurum kaydı gerçekten silinirse puanının anlamı
             * kalmaz → cascade. Puanı YAZAN yetkili silinirse puan durmalı →
             * null + `degerlendiren_ad`.
             */
            $t->foreignId('kurum_id')->nullable()->constrained('kurumlar')->cascadeOnDelete();
            $t->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();

            // Kişi hedefinin KALICI anahtarı; küçük harfe indirgenmiş saklanır.
            $t->string('eposta', 150)->nullable();

            // Kayıt silinse bile ekranda kimin puanı olduğu okunsun.
            $t->string('hedef_ad', 160)->nullable();

            $t->unsignedSmallInteger('puan');            // 1..5
            $t->text('not')->nullable();

            $t->foreignId('degerlendiren_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('degerlendiren_ad', 120)->nullable();   // aktör silinse de ad kalsın

            $t->timestamps();

            $t->index('kurum_id');
            $t->index('eposta');
        });

        // Tek güncel puan -- KISMİ benzersiz indeks (iki hedef tipi tek tabloda).
        DB::statement("CREATE UNIQUE INDEX degerlendirmeler_kurum_benzersiz
            ON degerlendirmeler (kurum_id) WHERE hedef_tip = 'kurum'");
        DB::statement("CREATE UNIQUE INDEX degerlendirmeler_kisi_benzersiz
            ON degerlendirmeler (eposta) WHERE hedef_tip = 'kisi'");

        // Ölçek dışı puan uygulama hatasıdır; veritabanı kabul etmesin.
        DB::statement('ALTER TABLE degerlendirmeler
            ADD CONSTRAINT degerlendirmeler_puan_araligi CHECK (puan BETWEEN 1 AND 5)');

        // Hedefsiz satır olmasın.
        DB::statement("ALTER TABLE degerlendirmeler ADD CONSTRAINT degerlendirmeler_hedef_butunlugu CHECK (
            (hedef_tip = 'kurum' AND kurum_id IS NOT NULL AND eposta IS NULL)
            OR (hedef_tip = 'kisi' AND eposta IS NOT NULL AND kurum_id IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('degerlendirmeler');
    }
};
