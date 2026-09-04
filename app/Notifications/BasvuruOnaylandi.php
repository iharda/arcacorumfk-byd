<?php

namespace App\Notifications;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Notifications\Concerns\PostaKuyrugu;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Plan v1.0 md.9 — onay bildirimi. Metin 04.09.2026 revizyonunda yeniden yazıldı.
 *
 * 🔑 Hesap ONAY anında açıldığı için (Revizyon md.3.2) bu e-posta aynı zamanda
 * hesabın kapısıdır: kullanıcı şifresini henüz belirlememişse imzalı, süreli
 * bir "şifremi belirle" bağlantısı taşır. Sistem düz metin şifre GÖNDERMEZ.
 *
 * ⏳ Bireysel başvurularda basın kartı PDF'i EK olarak gidecek (04. aşama).
 */
class BasvuruOnaylandi extends Notification implements ShouldQueue
{
    use PostaKuyrugu, Queueable;

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

    /**
     * 🪤 İmzadaki satır sonları MARKDOWN kuralına göre: satır sonundaki İKİ
     * BOŞLUK `<br>` üretir. `<br>` yazılamaz -- şablon `{{ }}` ile kaçırdığı
     * için ekranda etiket olarak görünür (BasvuruAlindi'de de aynı tuzak).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $kurumsal = $this->basvuru->tur === BasvuruTuru::Kurum;
        $sifat = $this->basvuru->tur->basvuruSifati();
        $unvan = $this->basvuru->kurum?->resmi_unvan;

        // "Mikron Medya adına yaptığınız medya kuruluşu başvurusu onaylandı."
        // Ünvan bir şekilde boşsa cümle " adına" ile başlamasın diye kişisel kip.
        $acilis = $kurumsal && filled($unvan)
            ? $unvan.' adına yaptığınız '.mb_strtolower($sifat, 'UTF-8').' başvurusu onaylandı.'
            : $sifat.' başvurunuz onaylandı.';

        $mesaj = (new MailMessage)
            ->subject('Başvurunuz onaylandı — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line($acilis);

        if (! $this->sifreBelirlenecek) {
            return $mesaj
                ->line($kurumsal
                    ? 'Kurum panelinizden çalışanlarınızın akreditasyon süreçlerini yönetebilirsiniz.'
                    : 'Basın kartınızı panelinizden görüntüleyebilirsiniz.')
                ->action($kurumsal ? 'Kurum paneline git' : 'Panele git', url($kurumsal ? '/kurum' : '/panel'))
                ->salutation($this->imza());
        }

        return $mesaj
            ->line($kurumsal
                ? 'Başvurunuzun onaylanmasıyla birlikte kurum hesabınız oluşturuldu. '
                    .'Şifrenizi oluşturarak panele giriş yapabilir ve çalışanlarınızın '
                    .'akreditasyon süreçlerini yönetebilirsiniz.'
                : 'Başvurunuzun onaylanmasıyla birlikte hesabınız oluşturuldu. '
                    .'Şifrenizi oluşturarak panele giriş yapabilir ve basın kartınızı '
                    .'görüntüleyebilirsiniz.')
            ->action('Şifre oluştur', URL::temporarySignedRoute(
                'hesap.aktivasyon',
                now()->addHours(48),
                ['kullanici' => $notifiable->ulid],
            ))
            ->line('Bu bağlantı 48 saat boyunca geçerlidir. Bağlantının süresi dolarsa '
                .'giriş sayfasındaki “Şifremi unuttum” seçeneğini kullanarak yeni bir '
                .'bağlantı isteyebilirsiniz.')
            ->salutation($this->imza());
    }

    /** Üç satırlık kulüp imzası -- BasvuruAlindi ile aynı biçim. */
    private function imza(): string
    {
        return "Saygılarımızla,  \nARCA Çorum FK  \nBasın Yönetim Sistemi";
    }
}
