<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Pages;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Notifications\EksikEvrakTalebi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Başvuru inceleme ekranı -- Plan v1.0 md.8.
 * Solda başvuru verisi ve evrak listesi, sağda seçili evrakın önizlemesi;
 * kararlar üst çubukta.
 */
class Inceleme extends Page
{
    /*
     * 🪤 Kayıt taşıyan özel bir kaynak sayfası bu trait'i KULLANMAK ZORUNDA.
     * Kendi `public Basvuru $record` özelliğini tanımlayıp mount()'ta elle
     * doldurmak yetmiyor: Filament kayda `getRecord()` ile ulaşıyor ve trait
     * yoksa sayfa sessizce 404 dönüyor.
     */
    use InteractsWithRecord;

    protected static string $resource = BasvuruResource::class;

    protected string $view = 'filament.yonetim.basvuru-inceleme';

    protected static ?string $title = 'Başvuru incelemesi';

    /** Sağ bölmede gösterilen evrakın ULID'i. */
    public ?string $seciliEvrak = null;

    public function mount(string|int $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorize('view', $this->record);

        $this->record->load(['kurum', 'kullanici', 'evraklar.turu', 'inceleyen', 'kararVeren']);
        $this->seciliEvrak = $this->record->evraklar->first()?->ulid;
    }

    public function getTitle(): string|Htmlable
    {
        // Hesap onaya kadar yok: ad başvurunun üstünden okunur.
        return $this->record->kurum?->resmi_unvan ?? $this->record->basvuranAdi();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->tur->etiket().' · '.$this->record->ulid;
    }

    public function evrakSec(string $ulid): void
    {
        $this->seciliEvrak = $ulid;
    }

    public function getSeciliEvrakModeliProperty()
    {
        return $this->record->evraklar->firstWhere('ulid', $this->seciliEvrak);
    }

    /**
     * Aynı kişinin ÖNCEKİ başvuruları. Reddedilen ya da ayrılan biri yeniden
     * başvurabildiği için yetkili "bunu daha önce görmüş müydük" sorusunun
     * cevabını ekranda görmeli.
     *
     * @return Collection<int, Basvuru>
     */
    public function getGecmisBasvurularProperty()
    {
        $eposta = $this->record->basvuranEpostasi();

        if ($eposta === null) {
            return collect();
        }

        /*
         * 🔑 Bağ E-POSTA üzerinden kurulur: hesap onay anında açıldığı için
         * (Revizyon md.3.2) kişinin eski başvurularının çoğunda `kullanici_id`
         * BOŞTUR. Yalnızca hesaba baksaydık "bunu daha önce görmüş müydük"
         * sorusunun cevabı hep boş çıkardı.
         */
        return Basvuru::query()
            ->where(fn (Builder $sorgu) => $sorgu
                ->where('basvuran_eposta', $eposta)
                ->when(
                    $this->record->kullanici_id,
                    fn (Builder $alt, int $id) => $alt->orWhere('kullanici_id', $id),
                ))
            ->whereKeyNot($this->record->getKey())
            ->latest('id')
            ->get();
    }

    /**
     * Eksik evrak talebinde işaretlenebilecek alanlar. Liste BAŞVURU TÜRÜNE
     * göre değişir: bireysel başvuruda "vergi dairesi" diye bir alan yok,
     * kurumsal başvuruda "basın kartı" yok.
     */
    public function isaretlenebilirAlanlar(): array
    {
        $ortak = [
            'Ad soyad' => 'Ad soyad',
            'Adres' => 'Adres',
            'İl / ilçe' => 'İl / ilçe',
            'Telefon' => 'Telefon',
            'E-posta' => 'E-posta',
            'Sosyal medya' => 'Sosyal medya',
        ];

        $alanlar = $this->record->tur === BasvuruTuru::Kurum
            ? [
                'Resmi ünvan' => 'Resmi ünvan',
                'Adres' => 'Adres',
                'İl / ilçe' => 'İl / ilçe',
                'Telefon' => 'Telefon',
                'E-posta' => 'E-posta',
                'Vergi dairesi' => 'Vergi dairesi',
                'Vergi numarası' => 'Vergi numarası',
                'Çalışan sayısı' => 'Çalışan sayısı',
                'Yayın platformları' => 'Yayın platformları',
                'Sosyal medya' => 'Sosyal medya',
                'Yetkili bilgileri' => 'Yetkili bilgileri',
            ]
            : $ortak + [
                'Kurum' => 'Kurum',
                'Basın kartı' => 'Basın kartı',
                '212 sigortası' => '212 sigortası',
                'Mesleki deneyim' => 'Mesleki deneyim',
            ];

        foreach (EvrakTuru::turIcin($this->record->tur) as $tur) {
            $alanlar[$tur->ad] = $tur->ad;
        }

        return $alanlar;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('incelemeyeAl')
                ->label('İncelemeye al')
                ->icon('heroicon-m-eye')
                ->visible(fn () => auth()->user()->can('incele', $this->record))
                ->action(fn () => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->incelemeyeAl($this->record),
                    'Başvuru incelemenize alındı.',
                )),

            Action::make('eksikEvrak')
                ->label('Eksik evrak iste')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel('Talebi gönder')
                ->visible(fn () => auth()->user()->can('eksikEvrakIste', $this->record))
                ->schema([
                    Repeater::make('notlar')
                        ->label('Düzeltilmesi istenen alanlar')
                        ->addActionLabel('Alan ekle')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->schema([
                            // Liste kısa (~15) — arama kutusu yerine düz açılır liste:
                            // daha az tıklama, klavyeyle daha hızlı.
                            Select::make('alan')
                                ->label('Alan')
                                ->options(fn () => $this->isaretlenebilirAlanlar())
                                ->native()
                                ->required(),
                            TextInput::make('aciklama')
                                ->label('Açıklama')
                                ->required()
                                ->maxLength(300),
                        ])
                        ->columns(2),
                    Textarea::make('mesaj')
                        ->label('Ek not (isteğe bağlı)')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data) {
                    $notlar = collect($data['notlar'] ?? [])
                        ->filter(fn ($s) => filled($s['alan'] ?? null))
                        ->mapWithKeys(fn ($s) => [$s['alan'] => $s['aciklama']])
                        ->all();

                    $this->calistir(
                        fn () => app(BasvuruAkisi::class)->eksikEvrakIste($this->record, $notlar, $data['mesaj'] ?? null),
                        'Düzeltme talebi gönderildi.',
                    );
                }),

            /*
             * Düzeltme bağlantısı e-postaya ulaşmadıysa ya da süresi dolduysa
             * başvuran çıkmazda kalmasın: yetkili yenisini gönderebilir.
             * Eski bağlantı ölür.
             */
            Action::make('duzeltmeBaglantisi')
                ->label('Düzeltme bağlantısını yeniden gönder')
                ->icon('heroicon-m-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Düzeltme bağlantısını yeniden gönder')
                ->modalDescription('Başvurana yeni bir bağlantı gider ve önceki bağlantı geçersiz olur.')
                ->modalSubmitActionLabel('Gönder')
                ->visible(fn () => $this->record->durum === BasvuruDurumu::EksikEvrak
                    && auth()->user()->can('eksikEvrakIste', $this->record))
                ->action(fn () => $this->calistir(function () {
                    $token = app(BasvuruBiletiAkisi::class)->yenidenGonder($this->record);

                    $this->record->bildirimHedefi()->notify(new EksikEvrakTalebi($this->record, $token));
                }, 'Yeni düzeltme bağlantısı gönderildi.')),

            Action::make('onayla')
                ->label('Onayla')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Başvuruyu onayla')
                // Hesap onayla birlikte açılır; bireysel başvuruda "kurum
                // akredite edilecek" metni yanlıştı.
                ->modalDescription(fn () => $this->record->tur === BasvuruTuru::Kurum
                    ? 'Kurum akredite edilecek, yetkiliye hesap açılacak ve bildirim gidecek. Bu adım geri alınamaz.'
                    : 'Başvurana hesap açılacak, akreditasyon ve kart numarası oluşacak. Bu adım geri alınamaz.')
                ->modalSubmitActionLabel('Onayla')
                ->visible(fn () => auth()->user()->can('kararVer', $this->record))
                ->action(fn () => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->onayla($this->record),
                    'Başvuru onaylandı.',
                )),

            Action::make('reddet')
                ->label('Reddet')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->modalSubmitActionLabel('Reddet')
                ->visible(fn () => auth()->user()->can('kararVer', $this->record))
                ->schema([
                    Textarea::make('gerekce')
                        ->label('Red gerekçesi')
                        ->helperText('Bu metin başvurana aynen iletilir.')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->reddet($this->record, $data['gerekce']),
                    'Başvuru reddedildi.',
                )),
        ];
    }

    /** Akış çağrılarını tek yerde sarmalar: hata bildirimi + kaydı tazeleme. */
    private function calistir(callable $is, string $basariMesaji): void
    {
        try {
            $is();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh()->load(['evraklar.turu', 'inceleyen', 'kararVeren', 'kurum', 'kullanici']);

        Notification::make()->title($basariMesaji)->success()->send();
    }

    public function getDurumRozeti(): array
    {
        return [
            'etiket' => $this->record->durum->etiket(),
            'renk' => $this->record->durum->renk(),
        ];
    }

    public function bekleyenMi(): bool
    {
        return in_array($this->record->durum, BasvuruDurumu::kuyruk(), true);
    }
}
