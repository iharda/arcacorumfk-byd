<?php

namespace App\Filament\Kurum\Ortak;

use App\Models\Basvuru;
use App\Models\Kurum;
use App\Servisler\BasvuruAkisi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Kurum teyidi eylemleri -- `Calisanlar` sayfası ve kurum panosundaki
 * `TeyitBekleyenler` widget'ı AYNI kodu kullansın diye burada.
 *
 * 🔑 Eylem, kaydı `$arguments['basvuru']` (ULID) ile alır; böylece hem sayfa
 * hem widget aynı imzayla çağırabiliyor.
 *
 * 🔒 Kapsam HER ÇAĞRIDA yeniden doğrulanır (`where('kurum_id', …)`): eylem
 * adresi doğrudan da tetiklenebilir, başka kurumun başvurusu teyit edilemesin.
 *
 * Kullanan sınıf `kurum(): ?Kurum` sağlamak zorundadır.
 */
trait TeyitEylemleri
{
    abstract public function kurum(): ?Kurum;

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

    protected function teyitVer(string $ulid, bool $onay, ?string $not = null): void
    {
        $basvuru = Basvuru::where('ulid', $ulid)
            ->where('kurum_id', $this->kurum()?->id)      // 🔒 kapsam: kendi kurumu
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
}
