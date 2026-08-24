<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hesabı ZATEN ETKİN olan birinin yeni başvurusu.
 *
 * 🔑 Buraya aktivasyon bağlantısı KOYMUYORUZ: hesap kullanılabilir durumda,
 * imzalı bir şifre bağlantısı göndermek formu dolduran herkese açık bir şifre
 * sıfırlama kapısı olurdu. Şifresini unutan zaten giriş ekranından yeniler.
 */
class YenidenBasvuruAlindi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Basvuru $basvuru) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Yeni başvurunuz alındı — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Mevcut hesabınıza yeni bir '.$this->basvuru->tur->etiket().' başvurusu eklendi.')
            ->line('Evraklarınızı yükleyip başvurunuzu göndermek için hesabınıza giriş yapın.')
            ->action('Giriş yap', url($notifiable->panelYolu().'/login'))
            ->line('Şifrenizi hatırlamıyorsanız giriş ekranındaki "Şifremi unuttum" bağlantısını kullanabilirsiniz.')
            ->line('Bu başvuruyu siz yapmadıysanız lütfen kulüple iletişime geçin.')
            ->salutation('ARCA Çorum FK');
    }
}
