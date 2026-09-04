<?php

namespace App\Filament\Yonetim\Ortak;

use App\Enums\BasvuruTuru;
use App\Enums\DegerlendirmePuani;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\DegerlendirmeAkisi;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use RuntimeException;
use Throwable;

/**
 * Puanlama eylemi -- TEK tanım, üç ekranda kullanılır (briefi md. A.7.2):
 * başvuru inceleme, kurumlar tablosu, kullanıcılar tablosu.
 *
 * 🔑 Neden modal, neden şeritte doğrudan tıklama değil: tabloda satır içi
 * Livewire tıklaması her satır için ayrı bileşen durumu demek; ayrıca notu
 * alacak yer kalmıyor. Şerit GÖSTERİM, modal DEĞİŞTİRME.
 *
 * 🔒 Eylem yalnızca `degerlendirme.yonet` yetkisiyle görünür; bu sınıf
 * BİLEREK `App\Filament\Yonetim` altındadır, kurum/üye paneline sızmasın.
 */
class DegerlendirmeEylemi
{
    /** Kurumlar tablosu satır aksiyonu. */
    public static function kurum(string $ad = 'degerlendir'): Action
    {
        return self::temel($ad)
            ->label(fn (Kurum $record) => self::etiket(self::akis()->kurumIcin($record)))
            ->modalHeading(fn (Kurum $record) => $record->resmi_unvan.' — değerlendirme')
            ->fillForm(fn (Kurum $record) => self::doldur(self::akis()->kurumIcin($record)))
            ->action(fn (Kurum $record, array $data) => self::calistir(
                fn () => self::akis()->kurumaYaz($record, (int) $data['puan'], $data['not'] ?? null),
            ));
    }

    /** Kullanıcılar tablosu satır aksiyonu -- hedef E-POSTA'dır, hesap değil. */
    public static function kisi(string $ad = 'degerlendir'): Action
    {
        return self::temel($ad)
            ->label(fn (User $record) => self::etiket(self::akis()->kisiIcin($record->email)))
            ->modalHeading(fn (User $record) => $record->name.' — değerlendirme')
            ->fillForm(fn (User $record) => self::doldur(self::akis()->kisiIcin($record->email)))
            ->action(fn (User $record, array $data) => self::calistir(
                fn () => self::akis()->kisiyeYaz(
                    $record->email, $record->name, (int) $data['puan'], $data['not'] ?? null,
                ),
            ));
    }

    /**
     * Kurum DETAY SAYFASI başlık aksiyonu -- M2.4 md.2.
     *
     * 🪤 `kurum()` ile aynı işi yapar ama kaydı kapanıştan alır: sayfa
     * aksiyonuna Filament `$record` enjekte etmez (bkz. `basvuru()`).
     * Tablo satırında `kurum()`, detay sayfasında bu kullanılır.
     *
     * @param  Closure(): Kurum  $kurum
     */
    public static function kurumSayfasi(Closure $kurum, string $ad = 'degerlendir'): Action
    {
        return self::temel($ad)
            ->label(fn () => self::etiket(self::akis()->kurumIcin($kurum())))
            ->modalHeading(fn () => $kurum()->resmi_unvan.' — değerlendirme')
            ->fillForm(fn () => self::doldur(self::akis()->kurumIcin($kurum())))
            ->action(fn (array $data) => self::calistir(
                fn () => self::akis()->kurumaYaz($kurum(), (int) $data['puan'], $data['not'] ?? null),
            ));
    }

    /**
     * Kullanıcı DETAY SAYFASI başlık aksiyonu -- M2.4 md.3.
     * Hedef E-POSTA'dır, hesap değil (bkz. `kisi()`).
     *
     * @param  Closure(): User  $kisi
     */
    public static function kisiSayfasi(Closure $kisi, string $ad = 'degerlendir'): Action
    {
        return self::temel($ad)
            ->label(fn () => self::etiket(self::akis()->kisiIcin($kisi()->email)))
            ->modalHeading(fn () => $kisi()->name.' — değerlendirme')
            ->fillForm(fn () => self::doldur(self::akis()->kisiIcin($kisi()->email)))
            ->action(fn (array $data) => self::calistir(
                fn () => self::akis()->kisiyeYaz(
                    $kisi()->email, $kisi()->name, (int) $data['puan'], $data['not'] ?? null,
                ),
            ));
    }

    /**
     * Başvuru inceleme sayfası aksiyonu.
     *
     * 🪤 Sayfa aksiyonuna Filament `$record` ENJEKTE ETMEZ (tablo satır
     * aksiyonundan farkı bu). Kayıt, sayfanın kendisinden gelen kapanışla
     * verilir: `DegerlendirmeEylemi::basvuru(fn () => $this->record)`.
     *
     * @param  Closure(): Basvuru  $basvuru
     */
    public static function basvuru(Closure $basvuru, string $ad = 'degerlendir'): Action
    {
        return self::temel($ad)
            ->label(fn () => self::etiket(self::akis()->basvuruIcin($basvuru())))
            ->modalHeading(fn () => self::hedefAdi($basvuru()).' — değerlendirme')
            // Kurumsal başvuruda kurum kaydı yoksa (taslak) hedef yoktur.
            ->visible(fn () => self::yetkiVar() && self::hedefliMi($basvuru()))
            ->fillForm(fn () => self::doldur(self::akis()->basvuruIcin($basvuru())))
            ->action(fn (array $data) => self::calistir(function () use ($basvuru, $data) {
                $kayit = $basvuru();
                $puan = (int) $data['puan'];
                $not = $data['not'] ?? null;

                if ($kayit->tur === BasvuruTuru::Kurum) {
                    self::akis()->kurumaYaz(
                        $kayit->kurum ?? throw new RuntimeException('Başvurunun kurum kaydı yok.'),
                        $puan, $not,
                    );

                    return;
                }

                self::akis()->kisiyeYaz(
                    $kayit->basvuranEpostasi() ?? throw new RuntimeException('Başvuruda e-posta adresi yok.'),
                    $kayit->basvuranAdi(), $puan, $not,
                );
            }));
    }

    /** Ortak modal: puan düğmeleri + not. */
    private static function temel(string $ad): Action
    {
        return Action::make($ad)
            ->icon('heroicon-m-star')
            ->color('gray')
            ->modalWidth(Width::Large)
            ->visible(fn () => self::yetkiVar())
            ->schema([
                ToggleButtons::make('puan')
                    ->label('Puan')
                    ->inline()
                    ->options(DegerlendirmePuani::secenekler())
                    ->colors(DegerlendirmePuani::renkler())
                    ->required(),

                Textarea::make('not')
                    ->label('Not (isteğe bağlı)')
                    ->rows(3)
                    ->maxLength(1000)
                    /*
                     * ⚖️ KVKK: bu not kişisel veridir. "Yalnızca kulüp görür"
                     * ifadesi ERİŞİM YETKİSİ demektir; veri sahibi md.11
                     * kapsamında talep ederse bu alan da kapsamdadır.
                     */
                    ->helperText('Bu not yalnızca kulüp yetkililerine görünür, başvurana iletilmez. '
                        .'Objektif ve mesleki gözlem yazın: not resmî kayıttır ve talep hâlinde '
                        .'ilgili kişiye açıklanması gerekebilir.'),
            ]);
    }

    private static function akis(): DegerlendirmeAkisi
    {
        return app(DegerlendirmeAkisi::class);
    }

    private static function yetkiVar(): bool
    {
        return auth()->user()?->can('degerlendirme.yonet') ?? false;
    }

    /** Yeni mi güncelleme mi -- düğme ve gönderim etiketi buna göre. */
    private static function etiket(?Degerlendirme $mevcut): string
    {
        return $mevcut === null ? 'Değerlendir' : 'Değerlendirmeyi güncelle';
    }

    /** @return array<string, mixed> */
    private static function doldur(?Degerlendirme $mevcut): array
    {
        return [
            'puan' => $mevcut?->puan->value,
            'not' => $mevcut?->not,
        ];
    }

    private static function hedefliMi(Basvuru $basvuru): bool
    {
        return $basvuru->tur === BasvuruTuru::Kurum
            ? $basvuru->kurum !== null
            : filled($basvuru->basvuranEpostasi());
    }

    private static function hedefAdi(Basvuru $basvuru): string
    {
        return $basvuru->tur === BasvuruTuru::Kurum
            ? ($basvuru->kurum->resmi_unvan ?? $basvuru->basvuranAdi())
            : $basvuru->basvuranAdi();
    }

    /** Servis hatası kullanıcıya bildirim olarak dönsün, 500 olmasın. */
    private static function calistir(callable $is): void
    {
        try {
            $is();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Değerlendirme kaydedildi.')->success()->send();
    }
}
