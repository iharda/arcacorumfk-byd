<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plan v1.0 md.9 — yeni duyuru / bülten / takvim değişikliği. */
class YeniIcerik extends Notification implements ShouldQueue
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
        public Model $icerik,
        public string $tur,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$konu, $giris, $yol] = match ($this->tur) {
            'duyuru' => ['Yeni duyuru', 'Kulüpten yeni bir duyuru yayınlandı.', '/panel/duyurular'],
            'bulten' => ['Yeni basın bülteni', 'Yeni bir basın bülteni yayınlandı.', '/panel/bultenler'],
            'antrenman' => ['Antrenman takvimi güncellendi', 'Basına açık antrenman takviminde bir güncelleme var.', '/panel/takvim'],
            default => ['Yeni içerik', 'Medya merkezinde yeni bir içerik var.', '/panel'],
        };

        $mesaj = (new MailMessage)
            ->subject($konu.' — ARCA Çorum FK')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line($giris)
            ->line('**'.$this->baslik().'**');

        if ($ozet = $this->ozet()) {
            $mesaj->line($ozet);
        }

        return $mesaj
            ->action('Panelde oku', url($yol))
            ->salutation('ARCA Çorum FK');
    }

    private function baslik(): string
    {
        if ($this->tur === 'antrenman') {
            $zaman = $this->icerik->baslangic_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i');

            return trim(($this->icerik->baslik ?: 'Antrenman').' · '.$zaman, ' ·');
        }

        return (string) $this->icerik->baslik;
    }

    private function ozet(): ?string
    {
        return match ($this->tur) {
            'duyuru' => $this->icerik->ozet,
            'antrenman' => $this->icerik->yer ? 'Yer: '.$this->icerik->yer : null,
            default => null,
        };
    }
}
