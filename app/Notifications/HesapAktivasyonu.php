<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Hesap aktivasyonu -- Plan v1.0 md.5.5.
 * 🔑 Sistem ŞİFRE ÜRETMEZ ve göndermez. Kullanıcı şifresini imzalı, süreli bir
 * bağlantıyla kendisi belirler; aynı adım e-posta doğrulaması yerine de geçer.
 */
class HesapAktivasyonu extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baglanti = URL::temporarySignedRoute(
            'hesap.aktivasyon',
            now()->addHours(48),
            ['kullanici' => $notifiable->ulid],
        );

        return (new MailMessage)
            ->subject('Hesabınızı etkinleştirin — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('Başvurunuz için bir hesap oluşturuldu. Aşağıdaki bağlantıdan şifrenizi belirleyip evraklarınızı yükleyebilirsiniz.')
            ->action('Şifremi belirle', $baglanti)
            ->line('Bu bağlantı **48 saat** geçerlidir.')
            ->line('Bu başvuruyu siz yapmadıysanız bu e-postayı yok sayabilirsiniz.')
            ->salutation('ARCA Çorum FK');
    }
}
