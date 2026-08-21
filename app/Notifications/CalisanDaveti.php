<?php

namespace App\Notifications;

use App\Models\Davet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan v1.0 md.5.2 "Yol B" — kurum başvuruyu başlatır, çalışan formu KENDİSİ
 * tamamlar. Kimlik ve fotoğrafı kişinin kendisinin yüklemesi esastır.
 *
 * 🔒 Ham token yalnızca bu e-postada geçer; veritabanında hash'i durur.
 * Gönderim `Notification::route('mail', ...)` ile yapılır — alıcının henüz
 * kayıtlı bir kullanıcısı yoktur.
 */
class CalisanDaveti extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Davet $davet,
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Basın akreditasyon başvurunuz başlatıldı — ARCA Çorum FK')
            ->greeting('Merhaba ' . $this->davet->ad_soyad . ',')
            ->line('**' . $this->davet->kurum?->resmi_unvan . '** sizin adınıza ARCA Çorum FK basın akreditasyon başvurusu başlattı.')
            ->line('Başvuruyu tamamlamak için bilgilerinizi girip evraklarınızı yüklemeniz gerekiyor.')
            ->action('Başvurumu tamamla', url('/davet/' . $this->token))
            ->line('Bu bağlantı **' . $this->davet->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y H:i') . '** tarihine kadar geçerlidir.')
            ->line('Bu başvurudan haberiniz yoksa bağlantıyı kullanmayın; kuruma bildirin.')
            ->salutation('ARCA Çorum FK');
    }
}
