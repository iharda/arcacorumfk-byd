<?php

namespace App\Filament\Yonetim\Pages;

use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Servisler\DenetimYazici;
use App\Servisler\KartNoUretici;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
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
            'duzeltme_bileti_gun' => (int) Ayar::al('duzeltme_bileti_gun', 14),
            'yeniden_basvuru_bekleme_gun' => (int) Ayar::al('yeniden_basvuru_bekleme_gun', 0),
            'kart_kodu_basin' => KartNoUretici::kod(BasvuruTuru::BasinMensubu),
            'kart_kodu_icerik' => KartNoUretici::kod(BasvuruTuru::IcerikUreticisi),
            'bolgeler' => collect((array) Ayar::al('bolgeler', []))
                ->map(fn ($ad, $anahtar) => ['anahtar' => $anahtar, 'ad' => $ad])
                ->values()
                ->all(),
            'varsayilan_bolgeler' => (array) Ayar::al('varsayilan_bolgeler', []),
            'kvkk_aydinlatma_metni' => Ayar::al('kvkk_aydinlatma_metni'),
            'kvkk_riza_metni' => Ayar::al('kvkk_riza_metni'),
            'gizlilik_metni' => Ayar::al('gizlilik_metni'),
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

                        TextInput::make('duzeltme_bileti_gun')
                            ->label('Düzeltme bağlantısı geçerlilik süresi')
                            ->helperText('Eksik evrak istendiğinde başvurana giden, hesap gerektirmeyen bağlantının ömrü.')
                            ->suffix('gün')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(60),

                        TextInput::make('yeniden_basvuru_bekleme_gun')
                            ->label('Reddedilen başvurudan sonra bekleme süresi')
                            ->suffix('gün')
                            ->helperText('0 = bekleme yok; reddedilen kişi hemen yeniden başvurabilir. Kuyruk aynı adaylarla dolarsa gün verin — süre son red kararından işler. Ayrılan kişilerde bekleme uygulanmaz.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(365),
                    ]),

                Section::make('Kart ve bölgeler')
                    ->description('Kart numarasındaki tür harfi ve kartın yetki verdiği alanlar. Değişiklik YENİ kartları etkiler; basılmış kartlar numarasını korur.')
                    ->schema([
                        TextInput::make('kart_kodu_basin')
                            ->label('Basın mensubu kart harfi')
                            ->helperText(fn () => 'Örnek: 2026-'.(KartNoUretici::kod(BasvuruTuru::BasinMensubu) ?: 'K').'-0042  ·  '
                                .self::kartSayisi(BasvuruTuru::BasinMensubu).' kart bu harfi kullanıyor')
                            ->required()
                            ->rule('regex:/^[A-HJ-NP-Z]$/')
                            // I ve O dışarıda: kart kapıda GÖZLE okunuyor, 1 ve 0 ile karışıyor.
                            ->validationMessages(['regex' => 'Tek büyük harf olmalı. I ve O kullanılamaz (1 ve 0 ile karışır).'])
                            ->maxLength(1),

                        TextInput::make('kart_kodu_icerik')
                            ->label('İçerik üreticisi kart harfi')
                            ->helperText(fn () => 'Örnek: 2026-'.(KartNoUretici::kod(BasvuruTuru::IcerikUreticisi) ?: 'B').'-0007  ·  '
                                .self::kartSayisi(BasvuruTuru::IcerikUreticisi).' kart bu harfi kullanıyor')
                            ->required()
                            ->rule('regex:/^[A-HJ-NP-Z]$/')
                            ->validationMessages(['regex' => 'Tek büyük harf olmalı. I ve O kullanılamaz (1 ve 0 ile karışır).'])
                            ->maxLength(1)
                            ->different('kart_kodu_basin'),

                        Repeater::make('bolgeler')
                            ->label('Bölgeler')
                            ->helperText('Kartın yetki verdiği alanlar. Kullanımda olan bir bölgeyi silmeyin — o yetkiye sahip kartlar bölgesiz kalır.')
                            ->addActionLabel('Bölge ekle')
                            ->schema([
                                TextInput::make('anahtar')
                                    ->label('Kod')
                                    ->helperText('Değiştirilmemeli; kayıtlarda bu değer duruyor.')
                                    ->required()
                                    ->rule('regex:/^[a-z0-9_]+$/')
                                    ->validationMessages(['regex' => 'Yalnızca küçük harf, rakam ve alt çizgi.'])
                                    ->maxLength(40),
                                TextInput::make('ad')
                                    ->label('Görünen ad')
                                    ->required()
                                    ->maxLength(60),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        /*
                         * 🔒 Güvenlikte doğru varsayılan KAPALI'dır
                         * (Düzeltme listesi md.9). `bolge_yetkileri` boş olan
                         * kart HER KAPIDAN geçer ve `AkreditasyonAkisi` onay
                         * anında hiç bölge atamıyordu: her yeni akreditasyon
                         * "her kapıdan geçer" olarak doğuyordu. Kısıtlı alanı
                         * olan kulüpte biri elle her karta bölge atayana
                         * kadar hiç kimseye kısıt yoktu.
                         */
                        CheckboxList::make('varsayilan_bolgeler')
                            ->label('Yeni akreditasyonların varsayılan bölgeleri')
                            ->helperText('Onay anında otomatik atanır. Boş bırakılırsa kart her kapıdan geçer.')
                            ->options(fn () => (array) Ayar::al('bolgeler', []))
                            ->columns(2)
                            ->columnSpanFull(),
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

    /** Bu harfle üretilmiş kaç kart var? Değiştirmeden önce görülsün. */
    private static function kartSayisi(BasvuruTuru $tur): int
    {
        $kod = KartNoUretici::kod($tur);

        return $kod ? Akreditasyon::where('tur_kodu', $kod)->count() : 0;
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

                // Kart harfleri ve bölgeler tek ayar altında toplanıyor;
                // formdaki ayrı alanlardan birleştiriyoruz.
                $veri['kart_tur_kodlari'] = [
                    BasvuruTuru::BasinMensubu->value => strtoupper($veri['kart_kodu_basin']),
                    BasvuruTuru::IcerikUreticisi->value => strtoupper($veri['kart_kodu_icerik']),
                ];
                $veri['bolgeler'] = collect($veri['bolgeler'] ?? [])
                    ->filter(fn ($b) => filled($b['anahtar'] ?? null))
                    ->mapWithKeys(fn ($b) => [$b['anahtar'] => $b['ad']])
                    ->all();
                unset($veri['kart_kodu_basin'], $veri['kart_kodu_icerik']);

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
