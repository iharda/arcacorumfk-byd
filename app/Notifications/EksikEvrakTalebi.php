<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan v1.0 md.9 — eksik evrak talebi, EKSİK ALAN LİSTESİYLE birlikte.
 * Başvuran yalnızca bu alanları düzeltebilir.
 */
class EksikEvrakTalebi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Basvuru $basvuru) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->subject('Başvurunuzda eksik/hatalı bilgi var — ARCA Çorum FK')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Başvurunuz incelendi ve aşağıdaki noktaların düzeltilmesi gerekiyor:');

        foreach ($this->basvuru->duzeltme_notlari ?? [] as $alan => $aciklama) {
            $mesaj->line('• **'.$alan.'** — '.$aciklama);
        }

        if (filled($this->basvuru->karar_gerekcesi)) {
            $mesaj->line('')->line($this->basvuru->karar_gerekcesi);
        }

        return $mesaj
            ->line('Panelinizden yalnızca işaretli alanları güncelleyip başvurunuzu yeniden gönderebilirsiniz.')
            ->action('Düzeltmeye git', url('/kurum'))
            ->salutation('ARCA Çorum FK');
    }
}
