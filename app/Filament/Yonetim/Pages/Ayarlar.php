<?php

namespace App\Filament\Yonetim\Pages;

use App\Models\Ayar;
use App\Servisler\DenetimYazici;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
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

                Section::make('KVKK metinleri')
                    ->description('İçerik kulüpten gelir. Boş bırakılan metin, kamuya açık sayfada "henüz yayımlanmadı" olarak görünür — boş sayfa gösterilmez.')
                    ->schema([
                        RichEditor::make('kvkk_aydinlatma_metni')
                            ->label('Aydınlatma metni')
                            ->helperText('Başvuru formundaki "Aydınlatma metnini okudum" onayı bu sayfaya bağlanır.')
                            ->toolbarButtons($this->araclar())
                            ->columnSpanFull(),

                        RichEditor::make('kvkk_riza_metni')
                            ->label('Açık rıza metni')
                            ->toolbarButtons($this->araclar())
                            ->columnSpanFull(),

                        RichEditor::make('gizlilik_metni')
                            ->label('Gizlilik politikası')
                            ->toolbarButtons($this->araclar())
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    private function araclar(): array
    {
        return ['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote', 'undo', 'redo'];
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

                    // Hukuki metinlerde "son güncelleme" tarihi de tutulur;
                    // kamuya açık sayfada gösteriliyor.
                    if (str_ends_with($anahtar, '_metni')) {
                        Ayar::yaz($anahtar.'_guncelleme', now()->toDateString());
                    }

                    // Metinler uzun; denetim kaydına tam gövdeyi değil
                    // değiştiği bilgisini yazıyoruz.
                    $kisalt = fn ($d) => is_string($d) && mb_strlen($d) > 200
                        ? mb_substr($d, 0, 200).'…'
                        : $d;

                    app(DenetimYazici::class)->yaz('ayar.degistirildi',
                        eski: [$anahtar => $kisalt($eski)], yeni: [$anahtar => $kisalt($yeni)]);
                }

                Notification::make()->title('Ayarlar kaydedildi.')->success()->send();
            });
    }
}
