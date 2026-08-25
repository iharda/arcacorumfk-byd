<?php

namespace App\Notifications;

use App\Models\Ayar;
use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plan v1.0 md.9 — red bildirimi, GEREKÇELİ. */
class BasvuruReddedildi extends Notification implements ShouldQueue
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
        $mesaj = (new MailMessage)
            ->subject('Başvurunuz hakkında — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line('Başvurunuz değerlendirildi ve olumsuz sonuçlandı.')
            ->line('**Gerekçe:** '.($this->basvuru->karar_gerekcesi ?: 'Belirtilmedi'));

        /*
         * 💀 Cümle doğruydu ama ADRES VERMİYORDU (Düzeltme listesi md.10).
         * Reddedilen kişinin hesabı YOKTUR (hesap yalnızca onayda açılır), o
         * yüzden panel içindeki "yeniden başvur" düğmesini de göremez —
         * e-postadaki bağlantı tek çıkış.
         */
        $bekleme = (int) Ayar::al('yeniden_basvuru_bekleme_gun', 0);

        if ($bekleme > 0) {
            $uygunTarih = ($this->basvuru->karar_at ?? now())->copy()->addDays($bekleme);

            return $mesaj
                ->line(sprintf(
                    'Durumunuzda bir değişiklik olursa **%s** tarihinden sonra yeniden başvurabilirsiniz.',
                    $uygunTarih->timezone('Europe/Istanbul')->format('d.m.Y')
                ))
                ->line('Başvuru adresi: '.$this->basvuru->tur->basvuruRotasi())
                ->salutation('ARCA Çorum FK');
        }

        return $mesaj
            ->line('Durumunuzda bir değişiklik olursa yeniden başvurabilirsiniz.')
            ->action('Yeniden başvur', $this->basvuru->tur->basvuruRotasi())
            ->salutation('ARCA Çorum FK');
    }
}
