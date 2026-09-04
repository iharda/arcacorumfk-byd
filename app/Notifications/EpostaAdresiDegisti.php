<?php

namespace App\Notifications;

use App\Notifications\Concerns\PostaKuyrugu;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hesabın e-posta adresi yetkili tarafından değiştirildi -- T13.
 *
 * 🔒 ESKİ ADRESE gider. E-posta giriş kimliğinin kendisi; sessizce
 * değiştirilirse hesabın sahibi kapının değiştiğini hiç öğrenemez. Yeni adres
 * de mesajda yazılı ki kişi yanlışlık varsa kime başvuracağını bilsin.
 */
class EpostaAdresiDegisti extends Notification implements ShouldQueue
{
    use PostaKuyrugu, Queueable;

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'posta'];
    }

    public function __construct(
        public string $eskiAdres,
        public string $yeniAdres,
        public string $ad,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->subject('Hesabınızın e-posta adresi değiştirildi — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->ad.',')
            ->line('Basın Yönetim Sistemi hesabınızın e-posta adresi kulüp yetkilisi tarafından güncellendi.')
            ->line('Eski adres: **'.$this->eskiAdres.'**')
            ->line('Yeni adres: **'.$this->yeniAdres.'**')
            ->line('Bundan sonra sisteme **yeni adresinizle** giriş yapacaksınız.')
            ->line('Bu değişiklikten haberiniz yoksa lütfen vakit kaybetmeden kulüple iletişime geçin.')
            ->salutation("Saygılarımızla,  \nARCA Çorum FK  \nBasın Yönetim Sistemi");

        $mesaj->viewData['dipnot'] = 'Bu e-posta, hesabınızdaki değişiklikten '
            .'haberdar olmanız için eski adresinize otomatik olarak gönderilmiştir.';

        return $mesaj;
    }
}
