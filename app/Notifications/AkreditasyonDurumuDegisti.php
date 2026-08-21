<?php

namespace App\Notifications;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plan v1.0 md.9 — iptal / askı / yeniden aktifleştirme bildirimi. */
class AkreditasyonDurumuDegisti extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Akreditasyon $akreditasyon,
        public ?string $gerekce = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Kart numaranız: **'.$this->akreditasyon->kart_no.'**');

        $mesaj = match ($this->akreditasyon->durum) {
            AkreditasyonDurumu::Iptal => $mesaj
                ->subject('Akreditasyonunuz iptal edildi — ARCA Çorum FK')
                ->line('Akreditasyonunuz **iptal edilmiştir**; kartınız kulüp girişlerinde geçerli değildir.'),
            AkreditasyonDurumu::Askida => $mesaj
                ->subject('Akreditasyonunuz askıya alındı — ARCA Çorum FK')
                ->line('Akreditasyonunuz **askıya alınmıştır**; askı kaldırılana kadar kartınız geçerli değildir.'),
            AkreditasyonDurumu::Aktif => $mesaj
                ->subject('Akreditasyonunuz yeniden etkin — ARCA Çorum FK')
                ->line('Akreditasyonunuz **yeniden etkinleştirildi**; kartınız geçerlidir.'),
        };

        if (filled($this->gerekce)) {
            $mesaj->line('**Gerekçe:** '.$this->gerekce);
        }

        return $mesaj->salutation('ARCA Çorum FK');
    }
}
