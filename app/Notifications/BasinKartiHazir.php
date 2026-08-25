<?php

namespace App\Notifications;

use App\Models\Akreditasyon;
use App\Models\Kart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Plan v1.0 md.9 — "Onay + kart" bildirimi, PDF kart EKLİ.
 * Kart panelden de yeniden indirilebilir.
 */
class BasinKartiHazir extends Notification implements ShouldQueue
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

    public function __construct(
        public Akreditasyon $akreditasyon,
        public Kart $kart,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->subject('Basın kartınız hazır — ARCA Çorum FK')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Akreditasyonunuz onaylandı ve basın kartınız üretildi.')
            ->line('**Kart no:** '.$this->akreditasyon->kart_no)
            ->line('Kartı telefonunuzdan gösterebilir veya çıktısını alabilirsiniz. Kapıda QR okutulur, görevli fotoğrafınızla eşleştirir.')
            ->action('Panelde görüntüle', url('/panel/kartim'))
            ->salutation('ARCA Çorum FK');

        // Ek: PDF kart. Dosya bir sebeple yoksa e-posta yine gitsin.
        if ($this->kart->pdf_yolu && Storage::disk($this->kart->disk)->exists($this->kart->pdf_yolu)) {
            $mesaj->attachData(
                Storage::disk($this->kart->disk)->get($this->kart->pdf_yolu),
                'basin-karti-'.$this->akreditasyon->kart_no.'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mesaj;
    }
}
