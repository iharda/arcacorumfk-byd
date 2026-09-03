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

    /**
     * Metin Cüneyt Bey revizyonunda (03.09.2026) yeniden yazıldı.
     *
     * 🪤 İmzadaki satır sonları MARKDOWN kuralına göre: satırın sonundaki
     * İKİ BOŞLUK `<br>` üretir. `<br>` yazılamaz, şablon `{{ }}` ile
     * kaçırdığı için ekranda etiket olarak görünürdü.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $sifat = $this->basvuru->tur->basvuruSifati();

        $mesaj = (new MailMessage)
            ->subject($sifat.' başvurunuz alındı — ARCA Çorum FK Basın Yönetim Sistemi')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line('**'.$sifat.'** başvurunuz başarıyla alınmıştır.')
            // 🔑 ULID DEGIL kisa numara: 26 hane telefonda okunmuyordu (Yusuf/IT).
            ->line('Başvuru numaranız: **'.$this->basvuru->basvuru_no.'**')
            ->line('Başvurunuz ve ilettiğiniz belgeler kulüp yetkilileri tarafından '
                .'değerlendirilecektir. Değerlendirme sonucu bu e-posta adresine gönderilecektir.')
            ->line('Başvurunuzla ilgili iletişimlerinizde başvuru numaranızı belirtmenizi rica ederiz.')
            ->salutation("Saygılarımızla,  \nARCA Çorum FK  \nBasın Yönetim Sistemi");

        $mesaj->viewData['dipnot'] = 'Bu e-posta, '.mb_strtolower($sifat, 'UTF-8')
            .' başvurunuzun alındığını bildirmek amacıyla otomatik olarak gönderilmiştir.';

        return $mesaj;
    }
}
