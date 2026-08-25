<?php

namespace App\Jobs;

use App\Notifications\YeniIcerik;
use App\Servisler\IcerikAkisi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Yeni duyuru / bülten / takvim bildirimi -- Plan v1.0 md.9.
 *
 * ⚠️ Alıcı sayısı yüzlerce olabilir. Kullanıcılar PARÇA PARÇA çekilir ve her
 * bildirim kuyruğa ayrı iş olarak düşer; tek bir dev iş SMTP'yi tıkamasın ve
 * yarıda kalırsa baştan başlamak zorunda kalmayalım.
 *
 * Model sınıfı + id taşıyoruz (modelin kendisini değil): iş kuyrukta beklerken
 * içerik düzenlenirse, gönderim anındaki HÂLİ gitsin.
 */
class IcerikBildirimiGonder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        /** @var class-string<Model> */
        public string $model,
        public int $kayitId,
        public string $tur,
    ) {
        // Bildirim kuyruğu ayrı: kart üretimi postayı bekletmesin.
        $this->onQueue('posta');
    }

    public function handle(): void
    {
        $icerik = $this->model::find($this->kayitId);

        // İçerik silinmiş ya da yayından kaldırılmışsa bildirim gönderme.
        if (! $icerik || ! $icerik->yayinda) {
            return;
        }

        IcerikAkisi::akrediteKullanicilar()
            ->select(['id', 'name', 'email'])
            ->chunkById(200, function ($kullanicilar) use ($icerik) {
                Notification::send($kullanicilar, new YeniIcerik($icerik, $this->tur));
            });
    }
}
