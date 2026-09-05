<?php

namespace App\Notifications;

use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Notifications\Concerns\PostaKuyrugu;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

/**
 * Akredite kişiden/kuruluştan belge talebi -- Cüneyt Bey revizyonu
 * (05.09.2026).
 *
 * 🔑 `EksikEvrakTalebi` KOPYALANMADI ÇÜNKÜ METNİ YANLIŞ OLURDU: o bildirim
 * karar öncesi yazıldı ve "tamamlamadan başvurunuz değerlendirmeye alınmaz"
 * diyor. Burada başvuru zaten onaylı, kart cepte ve turnikeden geçiyor.
 * Kartın DURDUĞUNU söylemek metnin en önemli cümlesi: aksi hâlde kişi
 * akreditasyonunun düştüğünü sanıp maça gelmez.
 *
 * 🔒 Ham token kuyruğa düz metin yazılmaz (bkz. EksikEvrakTalebi).
 */
class BelgeTalebi extends Notification implements ShouldQueue
{
    use PostaKuyrugu, Queueable;

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'posta', 'database' => 'posta'];
    }

    private string $sifreliToken;

    public function __construct(
        public Basvuru $basvuru,
        public BasvuruDuzeltmesi $talep,
        string $token,
    ) {
        $this->sifreliToken = Crypt::encryptString($token);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->subject('Akreditasyonunuz için belge talebi — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line('Akreditasyonunuz kapsamında aşağıdaki belge veya bilgi isteniyor:');

        foreach ($this->talep->talep_notlari as $anahtar => $aciklama) {
            $mesaj->line('• **'.$this->basvuru->duzeltmeEtiketi($anahtar).'** — '.$aciklama);
        }

        if (filled($this->talep->talep_gerekcesi)) {
            $mesaj->line('')->line($this->talep->talep_gerekcesi);
        }

        /*
         * 🔑 EN ÖNEMLİ CÜMLE. Kişi "belge istendi" e-postasını akreditasyonun
         * düştüğü anlamına gelecek şekilde okuyabilir; kartı cebinde durduğu
         * hâlde maça gelmemesi bu yüzden olur.
         */
        $mesaj->line('**'.$this->basvuru->belgeTalebiGuvencesi().'**; bu talep '
            .'akreditasyonunuzu askıya almaz, giriş yetkinizi etkilemez.');

        if ($this->talep->son_tarih !== null) {
            $mesaj->line('Belgeyi **'
                .$this->talep->son_tarih->timezone('Europe/Istanbul')->format('d.m.Y')
                .'** tarihine kadar göndermenizi rica ederiz.');
        }

        return $mesaj
            ->line('Yükleme için hesap açmanıza ya da giriş yapmanıza gerek yok:')
            ->action('Belgeyi gönder', route('basvuru.duzelt', [
                'token' => Crypt::decryptString($this->sifreliToken),
            ]))
            ->line('Bağlantı tek kullanımlıktır; süresi dolarsa kulüple iletişime geçin.')
            ->salutation('ARCA Çorum FK');
    }
}
