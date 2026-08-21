<?php

use App\Console\Commands\EvrakImha;
use Illuminate\Support\Facades\Schedule;

/*
 * Zamanlanmış işler. Sunucuda `/etc/cron.d/byd-scheduler` dakikada bir
 * `schedule:run` çağırır (kullanıcı: byd, root DEĞİL).
 */

// KVKK: saklama süresi dolan kimlik/çalışma belgesi dosyaları silinir.
Schedule::command(EvrakImha::class)->dailyAt('03:20')->onOneServer();
