<?php

namespace App\Filament\Yonetim\Ortak;

use App\Enums\AkreditasyonDurumu;
use App\Models\Akreditasyon;
use App\Servisler\AkreditasyonAkisi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Akreditasyonun durumunu değiştiren eylemler -- TEK TANIM.
 *
 * 🔑 Aynı üç karar iki ekrandan veriliyor: Akreditasyonlar listesinin satır
 * menüsü ve akreditasyon DETAY sayfası. Kopyalansalardı kip metinleri, yetki
 * adları ve durum koşulları er geç birbirinden ayrılır, iki ekran aynı kayda
 * farklı davranırdı.
 *
 * 🪤 Kapanışlar `Akreditasyon $record` TİPİYLE kaydı ister: Filament kaydı hem
 * tablo satırında hem de `InteractsWithRecord` kullanan detay sayfasında bu
 * yolla enjekte ediyor. Somut bir kayda kapanmak (fn () => $kayit) tabloyu
 * bozardı -- orada eylem satır başına değil BİR KEZ kurulur.
 */
class AkreditasyonEylemleri
{
    /** Geri alınabilir kapatma: kart askı süresince turnikeden geçmez. */
    public static function askiyaAl(): Action
    {
        return Action::make('askiyaAl')
            ->label('Askıya al')
            ->icon('heroicon-m-pause-circle')
            ->color('warning')
            ->visible(fn (Akreditasyon $record) => $record->durum === AkreditasyonDurumu::Aktif
                && auth()->user()->can('akreditasyon.aski'))
            ->schema([
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(3)->maxLength(500),
            ])
            ->modalDescription('Kart askı süresince turnikeden GEÇMEZ. Askı sonradan kaldırılabilir.')
            ->action(fn (Akreditasyon $record, array $data) => self::calistir(
                fn () => app(AkreditasyonAkisi::class)->askiyaAl($record, $data['gerekce']),
                'Akreditasyon askıya alındı.',
            ));
    }

    /** Askının kaldırılması -- "yeniden verme". İptalden dönüş YOKTUR. */
    public static function askiyiKaldir(): Action
    {
        return Action::make('yenidenAktif')
            ->label('Askıyı kaldır')
            ->icon('heroicon-m-play-circle')
            ->color('success')
            ->requiresConfirmation()
            // Sonucu olan her eylem ne olacagini yazar; Filament'in
            // "emin misiniz" varsayilani birakilmaz. (Saha notlari E1.)
            ->modalDescription('Kart bir sonraki okutmada yeniden geçerli olur. Askıya alma gerekçesi denetim kaydında kalır.')
            ->visible(fn (Akreditasyon $record) => $record->durum === AkreditasyonDurumu::Askida
                && auth()->user()->can('akreditasyon.aski'))
            ->action(fn (Akreditasyon $record) => self::calistir(
                fn () => app(AkreditasyonAkisi::class)->yenidenAktiflestir($record),
                'Akreditasyon yeniden etkin.',
            ));
    }

    /** 🔻 Geri alınamaz. Kişi yeniden başvurmadan geri dönüş yoktur. */
    public static function iptalEt(): Action
    {
        return Action::make('iptal')
            ->label('İptal et')
            ->icon('heroicon-m-no-symbol')
            ->color('danger')
            ->visible(fn (Akreditasyon $record) => $record->durum !== AkreditasyonDurumu::Iptal
                && auth()->user()->can('akreditasyon.iptal'))
            ->schema([
                Textarea::make('neden')->label('İptal nedeni')->required()->rows(3)->maxLength(500),
            ])
            // Geri alınamaz bir adım: sonucu açıkça yaz.
            ->modalDescription('Kart kalıcı olarak geçersizleşir ve turnike erişimi anında kapanır. Geri alınamaz; kişi yeniden başvurmalıdır.')
            ->modalSubmitActionLabel('İptal et')
            ->action(fn (Akreditasyon $record, array $data) => self::calistir(
                fn () => app(AkreditasyonAkisi::class)->iptalEt($record, $data['neden']),
                'Akreditasyon iptal edildi.',
            ));
    }

    /** Akış çağrılarını sarmalar: hata ekrana bildirim olarak düşer. */
    private static function calistir(callable $is, string $mesaj): void
    {
        try {
            $is();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($mesaj)->success()->send();
    }
}
