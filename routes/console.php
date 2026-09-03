<?php

use App\Console\Commands\EvrakImha;
use App\Console\Commands\EvrakTaslagiTemizle;
use Illuminate\Support\Facades\Schedule;

/*
 * Zamanlanmış işler. Sunucuda `/etc/cron.d/bys-scheduler` dakikada bir
 * `schedule:run` çağırır (kullanıcı: bys, root DEĞİL).
 */

// KVKK: saklama süresi dolan kimlik/çalışma belgesi dosyaları silinir.
Schedule::command(EvrakImha::class)->dailyAt('03:20')->onOneServer();

// Yarım kalan başvuru formlarından kalan geçici evrak taslakları
// (KVKK: aralarında kimlik belgesi var, diskte beklemesinler).
Schedule::command(EvrakTaslagiTemizle::class)->hourly()->onOneServer();
