<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan v1.0 md.9 — "Başvuru alındı" bildirimi. Kuyruk üzerinden gider.
 *
 * 🔑 Panele bağlantı YOK: hesap onay anında açılır (Revizyon md.1), başvuranın
 * girebileceği bir panel henüz yoktur. Bildirim hesapsız da gidebildiği için
 * (`Basvuru::bildirimHedefi()`) metin "siz" diline göre yazılır.
 */
class BasvuruAlindi extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * 🔑 Bildirimler POSTA kuyruğunda: kart üretimi onları bekletmesin
     * (Düzeltme listesi md.7).
     *
     * 💣 `public $queue = 'posta'` YAZILAMAZ: `Illuminate\Bus\Queueable`
     * trait'i aynı adda bir özellik tanımlıyor ve varsayılan değeri farklı
     * olduğu için PHP "incompatible" diyip ÖLÜMCÜL hata veriyor.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'posta', 'database' => 'posta'];
    }

    public function __construct(public Basvuru $basvuru) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Başvurunuz alındı — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line('**'.$this->basvuru->tur->etiket().'** başvurunuz tarafımıza ulaştı.')
            ->line('Başvuru numaranız: **'.$this->basvuru->ulid.'**')
            ->line('Evraklarınızla birlikte inceleme kuyruğuna alındı.')
            ->line('Yetkili incelemesi tamamlandığında sonuç bu e-posta adresine bildirilecektir.')
            ->salutation('ARCA Çorum FK');
    }
}
