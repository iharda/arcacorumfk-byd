<?php

namespace App\Notifications;

use App\Models\Basvuru;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

/**
 * Plan v1.0 md.9 — eksik evrak talebi, EKSİK ALAN LİSTESİYLE birlikte.
 *
 * 🔑 Düzeltme artık PANEL GEREKTİRMEZ (Revizyon md.3.3): e-postadaki geçici
 * bağlantı, yalnızca işaretli alanları açan kamuya açık bir sayfaya götürür.
 *
 * 🔒 Ham token kuyruğa DÜZ METİN yazılmaz. Bildirim `ShouldQueue` olduğu için
 * gövdesi Redis'te ve hata durumunda `failed_jobs` tablosunda durur; token
 * orada şifreli bekler, e-postaya yazılırken çözülür.
 */
class EksikEvrakTalebi extends Notification implements ShouldQueue
{
    use Queueable;

    private string $sifreliToken;

    public function __construct(public Basvuru $basvuru, string $token)
    {
        $this->sifreliToken = Crypt::encryptString($token);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mesaj = (new MailMessage)
            ->subject('Başvurunuzda eksik/hatalı bilgi var — ARCA Çorum FK')
            ->greeting('Merhaba '.$this->basvuru->basvuranAdi().',')
            ->line('Başvurunuz incelendi ve aşağıdaki noktaların düzeltilmesi gerekiyor:');

        foreach ($this->basvuru->duzeltme_notlari ?? [] as $alan => $aciklama) {
            $mesaj->line('• **'.$alan.'** — '.$aciklama);
        }

        if (filled($this->basvuru->karar_gerekcesi)) {
            $mesaj->line('')->line($this->basvuru->karar_gerekcesi);
        }

        $bilet = $this->basvuru->acikBilet();

        return $mesaj
            ->line('Aşağıdaki bağlantıdan yalnızca bu alanları düzeltip başvurunuzu yeniden gönderebilirsiniz. Hesap açmanıza ya da giriş yapmanıza gerek yok.')
            ->action('Başvurumu düzelt', route('basvuru.duzelt', [
                'token' => Crypt::decryptString($this->sifreliToken),
            ]))
            ->line($bilet
                ? 'Bağlantı **'.$bilet->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y').'** tarihine kadar ve **tek kullanımlık**tır.'
                : 'Bağlantı tek kullanımlıktır.')
            ->line('Süresi dolarsa kulüple iletişime geçin, yeni bağlantı gönderilir.')
            ->salutation('ARCA Çorum FK');
    }
}
