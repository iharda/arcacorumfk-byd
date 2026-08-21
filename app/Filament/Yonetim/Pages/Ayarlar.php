<?php

namespace App\Filament\Yonetim\Pages;

use App\Models\Ayar;
use App\Servisler\DenetimYazici;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Sistem ayarları -- Plan v1.0 md.8 ("kurum teyidi aç/kapa vb.").
 * Her değişiklik denetim kaydına eski → yeni değeriyle düşer (md.10).
 */
class Ayarlar extends Page
{
    protected string $view = 'filament.yonetim.ayarlar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Ayarlar';

    protected static ?string $title = 'Sistem ayarları';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public array $veri = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ayar.yonet') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'kurum_teyidi_istensin' => (bool) Ayar::al('kurum_teyidi_istensin', false),
            'davet_gecerlilik_gun' => (int) Ayar::al('davet_gecerlilik_gun', 7),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('veri')
            ->components([
                Section::make('Başvuru akışı')
                    ->schema([
                        Toggle::make('kurum_teyidi_istensin')
                            ->label('Kurum teyidi istensin')
                            ->helperText('Açıkken, kendisi başvuran basın mensubunun başvurusu önce kurumunun teyidini bekler; kurum onaylamadan kulüp incelemesine geçmez. Kurum kendi başlattığı başvurularda teyit istenmez.'),

                        TextInput::make('davet_gecerlilik_gun')
                            ->label('Davet bağlantısı geçerlilik süresi')
                            ->suffix('gün')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(60),
                    ]),
            ]);
    }

    public function kaydetAction(): Action
    {
        return Action::make('kaydet')
            ->label('Kaydet')
            ->action(function () {
                $veri = $this->form->getState();

                foreach ($veri as $anahtar => $yeni) {
                    $eski = Ayar::al($anahtar);
                    if ($eski === $yeni) {
                        continue;
                    }

                    Ayar::yaz($anahtar, $yeni);

                    app(DenetimYazici::class)->yaz('ayar.degistirildi',
                        eski: [$anahtar => $eski], yeni: [$anahtar => $yeni]);
                }

                Notification::make()->title('Ayarlar kaydedildi.')->success()->send();
            });
    }
}
