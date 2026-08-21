<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plan v1.0 md.9 — "Başvuru alındı" bildirimi. Kuyruk üzerinden gider. */
class BasvuruAlindi extends Notification implements ShouldQueue
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
            ->subject('Başvurunuz alındı — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('**'.$this->basvuru->tur->etiket().'** başvurunuz tarafımıza ulaştı.')
            ->line('Başvuru numaranız: **'.$this->basvuru->ulid.'**')
            ->line('Yetkili incelemesi tamamlandığında sonuç e-posta ile bildirilecektir.')
            ->action('Başvurumu görüntüle', url('/kurum'))
            ->salutation('ARCA Çorum FK');
    }
}
