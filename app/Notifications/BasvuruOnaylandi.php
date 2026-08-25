<?php

namespace App\Notifications;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Plan v1.0 md.9 — onay bildirimi.
 *
 * 🔑 Hesap ONAY anında açıldığı için (Revizyon md.3.2) bu e-posta aynı zamanda
 * hesabın kapısıdır: kullanıcı şifresini henüz belirlememişse imzalı, süreli
 * bir "şifremi belirle" bağlantısı taşır. Sistem düz metin şifre GÖNDERMEZ.
 *
 * ⏳ Bireysel başvurularda basın kartı PDF'i EK olarak gidecek (04. aşama).
 */
class BasvuruOnaylandi extends Notification implements ShouldQueue
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
        public Basvuru $basvuru,
        public bool $sifreBelirlenecek = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kurumsal = $this->basvuru->tur === BasvuruTuru::Kurum;

        $mesaj = (new MailMessage)
            ->subject('Başvurunuz onaylandı — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line($kurumsal
                ? '**'.$this->basvuru->kurum?->resmi_unvan.'** akredite edildi.'
                : 'Akreditasyon başvurunuz onaylandı.')
            ->line($kurumsal
                ? 'Kurum panelinizden çalışanlarınız için başvuru başlatabilirsiniz.'
                : 'Basın kartınız panelinizden görüntülenebilir.');

        if (! $this->sifreBelirlenecek) {
            return $mesaj
                ->action($kurumsal ? 'Kurum paneline git' : 'Panele git', url($kurumsal ? '/kurum' : '/panel'))
                ->salutation('ARCA Çorum FK');
        }

        return $mesaj
            ->line('Hesabınız onayla birlikte açıldı. Şifrenizi aşağıdaki bağlantıdan belirleyip panelinize girebilirsiniz.')
            ->action('Şifremi belirle', URL::temporarySignedRoute(
                'hesap.aktivasyon',
                now()->addHours(48),
                ['kullanici' => $notifiable->ulid],
            ))
            ->line('Bu bağlantı **48 saat** geçerlidir. Süresi dolarsa giriş sayfasındaki "şifremi unuttum" adımından yeni bağlantı isteyebilirsiniz.')
            ->salutation('ARCA Çorum FK');
    }
}
