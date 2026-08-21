<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plan v1.0 md.9 — red bildirimi, GEREKÇELİ. */
class BasvuruReddedildi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Basvuru $basvuru) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Başvurunuz hakkında — ARCA Çorum FK')
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('Başvurunuz değerlendirildi ve olumsuz sonuçlandı.')
            ->line('**Gerekçe:** ' . ($this->basvuru->karar_gerekcesi ?: 'Belirtilmedi'))
            ->line('Durumunuzda bir değişiklik olursa yeniden başvurabilirsiniz.')
            ->salutation('ARCA Çorum FK');
    }
}
