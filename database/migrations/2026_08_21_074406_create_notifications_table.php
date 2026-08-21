<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel ici bildirimler (Filament databaseNotifications).
 *
 * 🪤 Laravel'in hazir govdesi `data` sutununu TEXT yapar. Filament okunmamis
 * bildirim sayisini `data->>'format'` ile sorgular; PostgreSQL'de ->> operatoru
 * TEXT uzerinde YOKTUR -> panel 500 doner. Bu yuzden jsonb.
 * (MySQL/MariaDB'ye tasinirsa jsonb otomatik json olur, sorun cikmaz.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
