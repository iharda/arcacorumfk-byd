<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Tables;

use App\Enums\BasvuruDurumu;
use App\Enums\DegerlendirmePuani;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\Kurum;
use App\Servisler\KurumAkreditasyonu;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KurumlarTable
{
    /**
     * ⚠️ `iptal` ile `iptal_edildi` AYRI (M1-A):
     *   iptal        = akredite edilmiş kurumun akreditasyonu KALDIRILDI;
     *                  "Akreditasyonu geri ver" eylemi yalnız buna açılır.
     *   iptal_edildi = kurumsal BAŞVURU düşürüldü, kurum hiç akredite olmadı.
     *   reddedildi   = kurumsal başvuru reddedildi.
     * Son ikisi eskiden yazılmıyordu; kurum sonsuza kadar "Beklemede" kalıyordu.
     */
    private const DURUMLAR = [
        'beklemede' => 'Beklemede',
        'akredite' => 'Akredite',
        'iptal' => 'İptal',
        'reddedildi' => 'Reddedildi',
        'iptal_edildi' => 'Başvuru iptal edildi',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            // Satırın kendisi detayı açar (S1 + T1'in aynı kuralı).
            ->recordUrl(fn (Kurum $record) => KurumResource::getUrl('detay', ['record' => $record]))
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'akreditasyonlar as aktif_kart_sayisi' => fn (Builder $query) => $query->where('durum', 'aktif'),
                ])
                // Değerlendirme sütunu satır başına sorgu açmasın.
                ->with('degerlendirme'))
            ->defaultSort('resmi_unvan')
            ->columns([
                TextColumn::make('resmi_unvan')
                    ->label('Ünvan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('il')
                    ->label('İl')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('akreditasyon_durumu')
                    ->label('Akreditasyon')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'akredite' => 'success',
                        'iptal', 'reddedildi' => 'danger',
                        'beklemede' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => self::DURUMLAR[$state] ?? $state),

                TextColumn::make('aktif_kart_sayisi')
                    ->label('Aktif kart')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('kontenjan')
                    ->label('Kontenjan')
                    ->placeholder('Sınırsız')
                    ->toggleable(),

                /*
                 * 🔒 Yalnızca kulüp tarafı. Sütun `visible()` ile gizlenir ama
                 * asıl güvence bu tablonun YÖNETİM panelinde olması: kurum ve
                 * üye panelinde bu veriyi getiren hiçbir sorgu yok.
                 */
                TextColumn::make('degerlendirme.puan')
                    ->label('Değerlendirme')
                    ->badge()
                    ->color(fn (?DegerlendirmePuani $state) => $state?->renk() ?? 'gray')
                    ->formatStateUsing(fn (?DegerlendirmePuani $state) => $state
                        ? $state->value.' · '.$state->etiket()
                        : '—')
                    ->placeholder('—')
                    /*
                     * 🪤 Nokta yazımlı ilişki sütununda çıplak `sortable()`
                     * JOIN üretmez; `order by degerlendirmeler.puan` geçersiz
                     * SQL olur. Sıralama ilişkili ALT SORGUYLA yapılır
                     * (`degerlendirmeler.kurum_id` indeksli).
                     */
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        Degerlendirme::query()
                            ->select('puan')
                            ->whereColumn('degerlendirmeler.kurum_id', 'kurumlar.id')
                            ->where('hedef_tip', Degerlendirme::HEDEF_KURUM)
                            ->limit(1),
                        $direction,
                    ))
                    ->visible(fn () => auth()->user()?->can('degerlendirme.yonet') ?? false),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y', 'Europe/Istanbul')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('akreditasyon_durumu')
                    ->label('Akreditasyon')
                    ->options(self::DURUMLAR),

                SelectFilter::make('degerlendirme')
                    ->label('Değerlendirme')
                    ->options(DegerlendirmePuani::secenekler() + ['yok' => 'Değerlendirilmemiş'])
                    ->visible(fn () => auth()->user()?->can('degerlendirme.yonet') ?? false)
                    ->query(function (Builder $query, array $data) {
                        $deger = $data['value'] ?? null;

                        if (blank($deger)) {
                            return $query;
                        }

                        return $deger === 'yok'
                            ? $query->whereDoesntHave('degerlendirme')
                            : $query->whereHas('degerlendirme',
                                fn (Builder $q) => $q->where('puan', (int) $deger));
                    }),

                /*
                 * 🟠 M4.3: M1'in ekran ayağı. "Beklemede" görünen kurumun
                 * başvuru tarafında ne olduğunu sormanın yolu yoktu; kurum
                 * durumu ile başvuru durumu ayrışınca (red/iptal artık kuruma
                 * yazılıyor ama eski kayıtlar için hâlâ geçerli) bu süzgeç
                 * "hangi kurumun başvurusu nerede takıldı"yı cevaplar.
                 */
                SelectFilter::make('son_basvuru_durumu')
                    ->label('Son başvuru durumu')
                    ->options(fn () => collect(BasvuruDurumu::cases())
                        ->mapWithKeys(fn (BasvuruDurumu $d) => [$d->value => $d->etiket()])
                        ->all() + ['yok' => 'Hiç başvurusu yok'])
                    ->query(function (Builder $query, array $data): Builder {
                        $deger = $data['value'] ?? null;

                        if (blank($deger)) {
                            return $query;
                        }

                        if ($deger === 'yok') {
                            return $query->whereDoesntHave('basvurular');
                        }

                        /*
                         * 🪤 "SON" başvuru: `whereHas` yalnızca "böyle bir
                         * başvurusu var mı" der. Kurumun eski bir reddi ve yeni
                         * bir onayı varsa ikisinde de eşleşirdi. En son kaydın
                         * durumu soruluyor.
                         */
                        return $query->whereIn('id', Basvuru::query()
                            ->select('kurum_id')
                            ->whereNotNull('kurum_id')
                            ->where('durum', $deger)
                            ->whereNotExists(fn ($alt) => $alt
                                ->selectRaw('1')
                                ->from('basvurular as sonraki')
                                ->whereColumn('sonraki.kurum_id', 'basvurular.kurum_id')
                                ->whereColumn('sonraki.id', '>', 'basvurular.id')
                                ->whereNull('sonraki.deleted_at')));
                    }),

                // 🟠 M4.3: bölgesel akreditasyon dağılımı.
                SelectFilter::make('il')
                    ->label('İl')
                    ->searchable()
                    ->options(fn () => Kurum::query()
                        ->whereNotNull('il')
                        ->distinct()
                        ->orderBy('il')
                        ->pluck('il', 'il')
                        ->all()),
            ])
            /*
             * 🔑 M4.4: süzgeçler tablonun üstünde ve oturumda kalıcı
             * (Akreditasyonlar ekranındaki referans kalite).
             */
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->recordActions([
                DegerlendirmeEylemi::kurum(),

                Action::make('akreditasyonuKaldir')
                    ->label('Akreditasyonu kaldır')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->visible(fn (Kurum $record) => $record->akrediteMi()
                        && auth()->user()->can('akredite', $record))
                    ->schema([
                        Textarea::make('gerekce')
                            ->label('Gerekçe')
                            ->required()
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->modalDescription('Kurumun çalışanları yeni başvuru yapamaz. Mevcut kartlar bu adımla İPTAL OLMAZ; onları ayrıca askıya alın.')
                    ->action(function (Kurum $record, array $data) {
                        app(KurumAkreditasyonu::class)->kaldir($record, $data['gerekce']);

                        Notification::make()->title('Akreditasyon kaldırıldı.')->success()->send();
                    }),

                // 🔁 SİMETRİ ŞART: kaldırma eylemi durumu 'iptal' yapıp kendini
                // gizliyordu, tersini yapan eylem yoktu -- kurum "İptal"de
                // kilitleniyor, çalışanları yeni başvuru yapamıyordu. Tek çıkış
                // veritabanına elle müdahaleydi. (Saha notları T6.)
                Action::make('akrediteEt')
                    ->label('Akreditasyonu geri ver')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    // ⚠️ YALNIZ 'iptal'den geri dönüş. 'beklemede' buraya girmez:
                    // oradaki akreditasyon başvuru onayıyla doğar (BasvuruAkisi),
                    // hesap açılması ve bildirim o akışa bağlı. Buradan akredite
                    // etmek kurumu yetkilisiz bırakırdı.
                    ->visible(fn (Kurum $record) => $record->akreditasyon_durumu === 'iptal'
                        && auth()->user()->can('akredite', $record))
                    ->fillForm(fn (Kurum $record) => ['kontenjan' => $record->kontenjan])
                    ->schema([
                        Textarea::make('gerekce')
                            ->label('Gerekçe')
                            ->required()
                            ->rows(3)
                            ->maxLength(500),

                        // Kontenjan başvuru kabulünü doğrudan etkiliyor ama
                        // değiştirilecek ekranı yoktu; geri verme anı doğal yeri.
                        TextInput::make('kontenjan')
                            ->label('Kontenjan')
                            ->helperText('Boş bırakılırsa sınırsız.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(999),
                    ])
                    ->modalDescription('Kurumun çalışanları yeniden başvuru yapabilir. İptal edilmiş kartlar bu adımla GERİ GELMEZ; gerekiyorsa yeniden üretin.')
                    ->modalSubmitActionLabel('Akreditasyonu geri ver')
                    ->action(function (Kurum $record, array $data) {
                        app(KurumAkreditasyonu::class)->geriVer(
                            $record,
                            $data['gerekce'],
                            filled($data['kontenjan'] ?? null) ? (int) $data['kontenjan'] : null,
                        );

                        Notification::make()->title('Kurum yeniden akredite edildi.')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Kayıtlı kurum yok')
            ->emptyStateDescription('Kurumlar başvuru formundan oluşur.');
    }
}
