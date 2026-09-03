<?php

namespace App\Filament\Yonetim\Resources\Akreditasyonlar\Tables;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruTuru;
use App\Jobs\KartUret;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\CsvDisaAktar;
use App\Servisler\DenetimYazici;
use App\Support\Telefon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class AkreditasyonlarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['kullanici', 'kurum', 'guncelKart']))
            ->defaultSort('kart_no', 'desc')
            ->columns([
                TextColumn::make('kart_no')
                    ->label('Kart no')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('kullanici.name')
                    ->label('Kişi')
                    ->description(fn (Akreditasyon $record) => $record->kullanici?->email)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('kurum.resmi_unvan')
                    ->label('Kurum')
                    ->placeholder('Bağımsız')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (AkreditasyonDurumu $state) => $state->renk())
                    ->formatStateUsing(fn (AkreditasyonDurumu $state) => $state->etiket()),

                // Satır başına 1-4 rozet satır yüksekliğini ikiye katlıyor ve
                // tabloyu yatay kaydırmaya sokuyordu; listede aranan bölge değil
                // kişi ve durum. Bilgi kaybolmuyor: "Bölge yetkisi" kipinde tam
                // hâliyle duruyor. (Saha notları T7.)
                TextColumn::make('bolge_yetkileri')
                    ->label('Bölgeler')
                    ->badge()
                    ->separator()
                    ->formatStateUsing(fn ($state) => ((array) Ayar::al('bolgeler', []))[$state] ?? $state)
                    ->placeholder('Tanımsız')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * 🪤 Eskiden kartin SURUMU basiliyordu (s1, s2); yetkiliye
                 * hicbir sey soylemiyordu. Soyleyen sey kartin HAZIR olup
                 * olmadigi: uretim kuyrukta calistigi icin "yeniden uret"
                 * dedikten sonra sonucu gorunecek tek yer burasi.
                 * (Saha notlari T8 + T10'un liste ayagi.)
                 */
                TextColumn::make('kart_hazir')
                    ->label('Kart')
                    ->badge()
                    ->state(fn (Akreditasyon $record) => match (true) {
                        $record->guncelKart?->uretildi_at !== null => 'Hazır',
                        $record->guncelKart !== null => 'Hazırlanıyor',
                        default => 'Üretilmedi',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Hazır' => 'success',
                        'Hazırlanıyor' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Üretim')
                    ->dateTime('d.m.Y', 'Europe/Istanbul')
                    ->sortable(),

                TextColumn::make('iptal_nedeni')
                    ->label('İptal nedeni')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            /*
             * Süzgeçler -- Yusuf revizyonu 25.08.2026: üye türü, kurum ve
             * tarih aralığı eklendi; durum en üste alındı.
             */
            /*
             * 🔑 Süzgeçler TABLONUN ÜSTÜNDE, açılır kutuda değil (Yusuf
             * revizyonu md.5: "durum filtresi var ama bu da yukarı taşınır").
             * Maç haftası bu liste sürekli süzülüyor; her seferinde huniye
             * tıklamak gereksiz bir adım.
             */
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->multiple()
                    ->options(fn () => collect(AkreditasyonDurumu::cases())
                        ->mapWithKeys(fn ($d) => [$d->value => $d->etiket()])->all()),

                /*
                 * 🪤 Üye türü akreditasyonda SÜTUN DEĞİL: kart numarasındaki
                 * tür harfi (`tur_kodu`) ayardan geliyor ve değişebilir.
                 * Süzgeç başvurunun türü üzerinden kurulur -- kalıcı olan o.
                 */
                SelectFilter::make('uye_turu')
                    ->label('Üye türü')
                    ->multiple()
                    ->options(fn () => collect(BasvuruTuru::cases())
                        ->reject(fn (BasvuruTuru $t) => $t === BasvuruTuru::Kurum)
                        ->mapWithKeys(fn (BasvuruTuru $t) => [$t->value => $t->etiket()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['values'] ?? []),
                        fn (Builder $q) => $q->whereHas('basvuru',
                            fn (Builder $b) => $b->whereIn('tur', $data['values'])),
                    )),

                SelectFilter::make('kurum_id')
                    ->label('Kurum')
                    ->relationship('kurum', 'resmi_unvan')
                    ->searchable()
                    ->preload(),

                Filter::make('tarih')
                    ->label('Tarih aralığı')
                    ->schema([
                        DatePicker::make('baslangic')->label('Başlangıç')->native(false),
                        DatePicker::make('bitis')->label('Bitiş')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        // 🪤 `whereDate` sütuna fonksiyon uygular ve indeksi
                        // kullanamaz (Düzeltme listesi md.17); aralık kullanıyoruz.
                        ->when($data['baslangic'] ?? null,
                            fn (Builder $q, $t) => $q->where('created_at', '>=', Carbon::parse($t)->startOfDay()))
                        ->when($data['bitis'] ?? null,
                            fn (Builder $q, $t) => $q->where('created_at', '<=', Carbon::parse($t)->endOfDay())))
                    ->indicateUsing(function (array $data): ?string {
                        $parcalar = array_filter([
                            filled($data['baslangic'] ?? null) ? Carbon::parse($data['baslangic'])->format('d.m.Y') : null,
                            filled($data['bitis'] ?? null) ? Carbon::parse($data['bitis'])->format('d.m.Y') : null,
                        ]);

                        return $parcalar === [] ? null : 'Tarih: '.implode(' — ', $parcalar);
                    }),
            ])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => app(CsvDisaAktar::class)->akit(
                        $livewire->getFilteredTableQuery()->with(['kullanici', 'kurum']),
                        'akreditasyonlar',
                        ['Kart no', 'Ad soyad', 'E-posta', 'Telefon', 'Kurum', 'Durum', 'Bölgeler', 'Üretim'],
                        fn ($a) => [
                            $a->kart_no,
                            $a->kullanici?->name,
                            $a->kullanici?->email,
                            Telefon::goster($a->kullanici?->telefon),
                            $a->kurum?->resmi_unvan ?? 'Bağımsız',
                            $a->durum->etiket(),
                            implode(', ', $a->bolge_yetkileri ?? []),
                            $a->created_at?->timezone('Europe/Istanbul')->format('d.m.Y'),
                        ],
                        // 🔒 Toplu kişisel veri indirme denetime düşer (Düzeltme listesi md.8).
                        olay: 'akreditasyon.disa_aktarildi',
                    )),
            ])
            /*
             * 🧷 Satir basina bes eylem yan yana duruyordu; "Iptal et"e ulasmak
             * icin sutun gizlemek gerekiyordu ve yatay kaydirmada Kart no ile
             * Kisi ekrandan cikiyordu -- yetkili kimin satirinda oldugunu
             * goremeden yikici eyleme basabiliyordu. Eylemler uc nokta
             * menusune girer, iptal en altta ve AYRI durur. (Saha notlari E2.)
             */
            ->recordActions([
                ActionGroup::make([
                    // İç içe ActionGroup + dropdown(false) = menüde ayırıcı çizgi.
                    ActionGroup::make([
                        Action::make('askiyaAl')
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
                            )),

                        Action::make('yenidenAktif')
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
                            )),

                        Action::make('bolgeYetkisi')
                            ->label('Bölge yetkisi')
                            ->icon('heroicon-m-map-pin')
                            ->visible(fn (Akreditasyon $record) => $record->durum !== AkreditasyonDurumu::Iptal
                                && auth()->user()->can('akreditasyon.aski'))
                            ->fillForm(fn (Akreditasyon $record) => ['bolgeler' => $record->bolge_yetkileri ?? []])
                            ->schema([
                                CheckboxList::make('bolgeler')
                                    ->label('Girebileceği bölgeler')
                                    ->helperText('Hiçbiri seçilmezse bölge kontrolü yapılmaz; kart geçerliyse her kapıdan geçer.')
                                    ->options(fn () => (array) Ayar::al('bolgeler', []))
                                    ->columns(2),
                            ])
                            ->action(function (Akreditasyon $record, array $data) {
                                $eski = $record->bolge_yetkileri;

                                DB::transaction(function () use ($record, $data, $eski) {
                                    $record->update(['bolge_yetkileri' => $data['bolgeler'] ?: null]);

                                    app(DenetimYazici::class)->yaz('akreditasyon.bolge_degisti', $record,
                                        eski: ['bolge_yetkileri' => $eski],
                                        yeni: ['bolge_yetkileri' => $data['bolgeler'] ?: null]);
                                });

                                // Bölgeler kartın üstünde yazıyor: kart yeniden üretilmeli.
                                KartUret::dispatch($record, bildirimGonder: false, tetikleyenId: auth()->id())->afterCommit();

                                Notification::make()
                                    ->title('Bölge yetkisi güncellendi, kart yeniden üretiliyor.')
                                    ->success()->send();
                            }),

                        Action::make('kartYenidenUret')
                            ->label('Kartı yeniden üret')
                            ->icon('heroicon-m-arrow-path')
                            ->requiresConfirmation()
                            ->modalDescription('Yeni sürüm üretilir, eskisi arşivlenir. QR ve kart numarası DEĞİŞMEZ.')
                            ->visible(fn (Akreditasyon $record) => $record->durum !== AkreditasyonDurumu::Iptal
                                && auth()->user()->can('kart.uret'))
                            ->action(function (Akreditasyon $record) {
                                KartUret::dispatch($record, bildirimGonder: false, tetikleyenId: auth()->id());

                                Notification::make()->title('Kart üretimi kuyruğa alındı.')->success()->send();
                            }),
                    ])->dropdown(false),

                    // 🔻 Geri alınamaz: menünün en altında ve AYRI dursun.
                    ActionGroup::make([
                        Action::make('iptal')
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
                            )),
                    ])->dropdown(false),

                ])
                    ->label('İşlemler')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Akreditasyon yok')
            ->emptyStateDescription('Onaylanan bireysel başvurulardan doğar.');
    }

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
