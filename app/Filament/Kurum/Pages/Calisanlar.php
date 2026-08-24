<?php

namespace App\Filament\Kurum\Pages;

use App\Models\Basvuru;
use App\Models\Davet;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\DavetAkisi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Kurum paneli — Çalışanlar. Plan v1.0 md.8 (Kurum paneli) ve md.5.4 (ayrılış).
 *
 * Üç iş bir arada:
 *   1. Kurum teyidi bekleyen başvurular (md.5.2)
 *   2. Çalışan listesi + ayrılış bildirimi → akreditasyon OTOMATİK iptal
 *   3. Davetler (Yol B)
 */
class Calisanlar extends Page
{
    protected string $view = 'filament.kurum.calisanlar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Çalışanlar';

    protected static ?string $title = 'Çalışanlar';

    protected static ?int $navigationSort = 2;

    /** Kurum akredite olmadan bu ekranın anlamı yok. */
    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->kurum?->akrediteMi() === true;
    }

    public function mount(): void
    {
        abort_unless($this->kurum() !== null, 404);
        abort_unless($this->kurum()->akrediteMi(), 403);
    }

    public function kurum(): ?Kurum
    {
        return Auth::user()?->kurum;
    }

    /** @return Collection<int, Basvuru> */
    public function getTeyitBekleyenlerProperty(): Collection
    {
        return Basvuru::with('kullanici')
            ->where('kurum_id', $this->kurum()->id)
            ->teyitBekleyen()
            ->orderBy('gonderildi_at')
            ->get();
    }

    /** @return Collection<int, User> */
    public function getCalisanlarProperty(): Collection
    {
        return User::with(['akreditasyon', 'basvurular' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('kurum_id', $this->kurum()->id)
            ->whereKeyNot(Auth::id())
            ->orderBy('ayrildi_at')       // ayrılanlar sona
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Davet> */
    public function getDavetlerProperty(): Collection
    {
        return Davet::where('kurum_id', $this->kurum()->id)
            ->whereNull('kullanildi_at')
            ->whereNull('iptal_at')
            ->where('gecerlilik_bitis', '>', now())
            ->orderByDesc('id')
            ->get();
    }

    /* ─────────────────── Eylemler ─────────────────── */

    public function davetEtAction(): Action
    {
        return Action::make('davetEt')
            ->label('Çalışan davet et')
            ->icon('heroicon-m-envelope')
            ->modalWidth(Width::Large)
            ->modalDescription('Kişiye bir bağlantı gönderilir; kimlik ve fotoğrafını kendisi yükler.')
            ->modalSubmitActionLabel('Daveti gönder')
            ->schema([
                TextInput::make('ad_soyad')->label('Ad soyad')->required()->minLength(3)->maxLength(120),
                TextInput::make('eposta')->label('E-posta')->email()->required()->maxLength(150),
            ])
            ->action(function (array $data) {
                try {
                    $sonuc = app(DavetAkisi::class)->olustur($this->kurum(), $data['ad_soyad'], $data['eposta']);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $this->baglantiyiGoster($sonuc['token'], $data['ad_soyad']);
            });
    }

    public function teyitAction(): Action
    {
        return Action::make('teyit')
            ->label('Teyit et')
            ->color('success')
            ->icon('heroicon-m-check')
            ->requiresConfirmation()
            ->modalHeading('Çalışanınız olduğunu teyit ediyor musunuz?')
            ->modalDescription('Teyidinizden sonra başvuru kulüp incelemesine geçer.')
            ->action(function (array $arguments) {
                $this->teyitVer($arguments['basvuru'] ?? '', true);
            });
    }

    public function teyitReddetAction(): Action
    {
        return Action::make('teyitReddet')
            ->label('Çalışanımız değil')
            ->color('danger')
            ->icon('heroicon-m-x-mark')
            ->schema([
                Textarea::make('not')->label('Açıklama (isteğe bağlı)')->rows(3)->maxLength(500),
            ])
            ->modalDescription('Başvuru düşer ve kulüp incelemesine hiç girmez.')
            ->action(function (array $arguments, array $data) {
                $this->teyitVer($arguments['basvuru'] ?? '', false, $data['not'] ?? null);
            });
    }

    public function ayrilisAction(): Action
    {
        return Action::make('ayrilis')
            ->label('Ayrıldı olarak işaretle')
            ->color('danger')
            ->icon('heroicon-m-arrow-right-start-on-rectangle')
            ->schema([
                Textarea::make('not')->label('Not (isteğe bağlı)')->rows(2)->maxLength(300),
            ])
            ->modalHeading('Ayrılış bildirimi')
            // Kullanıcı ne olacağını ÖNCEDEN bilmeli: bu adım geri alınamaz.
            ->modalDescription('Kişinin akreditasyonu ANINDA iptal edilir ve kulüp girişi kapanır. Geri alınamaz; kişi yeniden başvurmalıdır.')
            ->modalSubmitActionLabel('Ayrılışı bildir')
            ->action(function (array $arguments, array $data) {
                $this->ayrilisBildir($arguments['kullanici'] ?? '', $data['not'] ?? null);
            });
    }

    public function davetYenidenGonderAction(): Action
    {
        return Action::make('davetYenidenGonder')
            ->label('Yeniden gönder')
            ->icon('heroicon-m-arrow-path')
            ->action(function (array $arguments) {
                $davet = $this->davetiBul($arguments['davet'] ?? '');

                try {
                    $token = app(DavetAkisi::class)->yenidenGonder($davet);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $this->baglantiyiGoster($token, $davet->ad_soyad);
            });
    }

    public function davetIptalAction(): Action
    {
        return Action::make('davetIptal')
            ->label('İptal et')
            ->color('danger')
            ->icon('heroicon-m-trash')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                app(DavetAkisi::class)->iptalEt($this->davetiBul($arguments['davet'] ?? ''));

                Notification::make()->title('Davet iptal edildi.')->success()->send();
            });
    }

    /* ─────────────────── Yardımcılar ─────────────────── */

    private function teyitVer(string $ulid, bool $onay, ?string $not = null): void
    {
        $basvuru = Basvuru::where('ulid', $ulid)
            ->where('kurum_id', $this->kurum()->id)      // 🔒 kapsam: kendi kurumu
            ->firstOrFail();

        try {
            app(BasvuruAkisi::class)->kurumTeyidiVer($basvuru, $onay, $not);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($onay ? 'Teyit verildi, başvuru kulüp incelemesine geçti.' : 'Başvuru düşürüldü.')
            ->success()->send();
    }

    /**
     * md.5.4: ayrılış → akreditasyon OTOMATİK iptal, onay adımı yok.
     * İkisi tek işlemde yürüsün diye iş AkreditasyonAkisi'nde; ekran yalnızca
     * kapsamı doğrular ve sonucu bildirir.
     */
    private function ayrilisBildir(string $ulid, ?string $not): void
    {
        $kullanici = User::where('ulid', $ulid)
            ->where('kurum_id', $this->kurum()->id)      // 🔒 kapsam
            ->firstOrFail();

        try {
            app(AkreditasyonAkisi::class)->kullaniciAyrildi($kullanici, $not);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        Notification::make()
            ->title('Ayrılış bildirildi, akreditasyon iptal edildi.')
            ->success()->send();
    }

    /**
     * Davet bağlantısını BİR KEZ gösterir.
     * E-posta gitmediğinde ("spam'e düşmüş", "adres yanlışmış") kurum
     * yetkilisi bağlantıyı elden iletebilsin diye. Kalıcı bildirim:
     * kopyalanmadan ekrandan kaybolmasın.
     */
    private function baglantiyiGoster(string $token, string $adSoyad): void
    {
        Notification::make()
            ->title($adSoyad.' için davet gönderildi')
            ->body('E-posta ulaşmazsa bu bağlantıyı iletebilirsiniz (yalnızca şimdi gösterilir):  '
                .url('/davet/'.$token))
            ->success()
            ->persistent()
            ->send();
    }

    private function davetiBul(string $ulid): Davet
    {
        return Davet::where('ulid', $ulid)
            ->where('kurum_id', $this->kurum()->id)      // 🔒 kapsam
            ->firstOrFail();
    }
}
