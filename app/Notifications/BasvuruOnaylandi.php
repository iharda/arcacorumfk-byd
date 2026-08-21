<?php

namespace App\Notifications;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan v1.0 md.9 — onay bildirimi.
 * ⏳ Bireysel başvurularda basın kartı PDF'i EK olarak gidecek (04. aşama).
 */
class BasvuruOnaylandi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Basvuru $basvuru) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kurumsal = $this->basvuru->tur === BasvuruTuru::Kurum;

        return (new MailMessage)
            ->subject('Başvurunuz onaylandı — ARCA Çorum FK')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line($kurumsal
                ? '**'.$this->basvuru->kurum?->resmi_unvan.'** akredite edildi.'
                : 'Akreditasyon başvurunuz onaylandı.')
            ->line($kurumsal
                ? 'Kurum panelinizden çalışanlarınız için başvuru başlatabilirsiniz.'
                : 'Basın kartınız panelinizden görüntülenebilir.')
            ->action($kurumsal ? 'Kurum paneline git' : 'Panele git', url($kurumsal ? '/kurum' : '/panel'))
            ->salutation('ARCA Çorum FK');
    }
}
