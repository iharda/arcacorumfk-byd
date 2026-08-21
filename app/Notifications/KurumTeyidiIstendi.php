<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan v1.0 md.5.2 — kişi kendisi başvurdu, kurumun "bu kişi çalışanımız"
 * demesi bekleniyor. Kurum cevaplamadan başvuru yetkili kuyruğuna girmez.
 */
class KurumTeyidiIstendi extends Notification implements ShouldQueue
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
            ->subject('Teyidiniz bekleniyor — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('**'.$this->basvuru->kullanici?->name.'** kurumunuz adına akreditasyon başvurusu yaptı.')
            ->line('Başvurunun kulüp incelemesine geçebilmesi için bu kişinin çalışanınız olduğunu teyit etmeniz gerekiyor.')
            ->action('Teyit ekranına git', url('/kurum/calisanlar'))
            ->salutation('ARCA Çorum FK');
    }
}
