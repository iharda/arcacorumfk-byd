<?php

namespace App\Notifications;

use App\Models\Akreditasyon;
use App\Models\Kart;
use Filament\Notifications\Notification as FilamentBildirim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Kartı ÜRETTİREN yetkiliye "iş bitti" bildirimi -- T10'un üçüncü katmanı.
 *
 * 🔑 Neden gerekiyor: kart üretimi kuyrukta çalışıyor, düğmeye basınca dönen
 * tek şey "kuyruğa alındı" idi; sonrası sessizdi. Yetkili "ne oldu?" diye
 * sayfayı yenilemek zorunda kalıyordu. Zil ikonu (databaseNotifications) üç
 * panelde de açık; soruyu kaynağında bitiren yer orası.
 *
 * ⚠️ Bu bildirim ÜYEYE gitmez; üyenin kendi bildirimi BasinKartiHazir.
 */
class KartUretimiTamamlandi extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['database' => 'posta'];
    }

    public function __construct(
        public Akreditasyon $akreditasyon,
        public Kart $kart,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentBildirim::make()
            ->title($this->akreditasyon->kart_no.' kartı hazır')
            ->body('Sürüm s'.$this->kart->surum.' üretildi.')
            ->icon('heroicon-o-identification')
            ->success()
            ->getDatabaseMessage();
    }
}
