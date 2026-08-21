<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Jobs\IcerikBildirimiGonder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Medya merkezi içerikleri -- Plan v1.0 md.8 ve md.9.
 * Duyuru / antrenman takvimi / bülten aynı davranışı paylaşır:
 * taslak → yayın → akredite kullanıcılara bildirim.
 */
class IcerikAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    /**
     * "Akredite kullanıcı" kimdir? Bildirim ve içerik erişimi bu tanıma göre.
     *  · aktif akreditasyonu olan basın mensubu / içerik üreticisi
     *  · akredite bir kurumun aktif yetkilisi
     * Ayrılmış ya da pasif hesaplar HARİÇ.
     */
    public static function akrediteKullanicilar(): Builder
    {
        return User::query()
            ->where('aktif', true)
            ->whereNull('ayrildi_at')
            ->where(fn (Builder $alt) => $alt
                ->whereHas('akreditasyon', fn (Builder $a) => $a->where('durum', AkreditasyonDurumu::Aktif->value))
                ->orWhereHas('kurum', fn (Builder $k) => $k->where('akreditasyon_durumu', 'akredite')));
    }

    /**
     * Yayına al. Bildirim YALNIZCA İLK yayında gider — bir yazım hatasını
     * düzeltip yeniden yayınlamak yüzlerce kişiye ikinci bir e-posta atmasın.
     */
    public function yayinla(Model $icerik, string $tur): void
    {
        DB::transaction(function () use ($icerik, $tur) {
            $ilkYayin = ! $icerik->bildirim_gonderildi;

            $icerik->forceFill([
                'yayinda'  => true,
                'yayin_at' => $icerik->yayin_at ?? now(),
            ])->save();

            $this->denetim->yaz("{$tur}.yayinlandi", $icerik, yeni: ['yayinda' => true]);

            if ($ilkYayin) {
                $icerik->forceFill(['bildirim_gonderildi' => true])->save();
                IcerikBildirimiGonder::dispatch($icerik::class, $icerik->getKey(), $tur)->afterCommit();
            }
        });
    }

    public function yayindanKaldir(Model $icerik, string $tur): void
    {
        $icerik->forceFill(['yayinda' => false])->save();

        $this->denetim->yaz("{$tur}.yayindan_kaldirildi", $icerik, yeni: ['yayinda' => false]);
    }
}
