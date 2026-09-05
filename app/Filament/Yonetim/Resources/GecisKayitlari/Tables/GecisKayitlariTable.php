<?php

namespace App\Filament\Yonetim\Resources\GecisKayitlari\Tables;

use App\Enums\GecisSonucu;
use App\Filament\Yonetim\Ortak\SiraSutunu;
use App\Filament\Yonetim\Resources\GecisKayitlari\GecisKaydiResource;
use App\Models\GecisKaydi;
use App\Servisler\CsvDisaAktar;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GecisKayitlariTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Satırın kendisi detayı açar (S1).
            ->recordUrl(fn (GecisKaydi $record) => GecisKaydiResource::getUrl('detay', ['record' => $record]))
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['akreditasyon.kullanici', 'kapiIstemcisi']))
            ->defaultSort('okundu_at', 'desc')
            ->deferLoading()          // maç günü tablo büyür; ilk açılış hızlı kalsın
            ->paginated([25, 50, 100])
            /*
             * 🔑 Bu ekran maç günü AÇIK DURUYOR: turnikelerde ne olduğunu
             * buradan izliyorlar. Elle yenilemek gerekiyorsa ekran ölü
             * demektir. 30 sn, bir okutmanın fark edilmesi için yeterince
             * sık; sayfalanmış sorgu için yeterince seyrek.
             */
            ->poll('30s')
            ->columns([
                SiraSutunu::yap(),

                TextColumn::make('okundu_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Istanbul')
                    ->sortable(),

                /*
                 * 🔑 Arama KISI ADINI da kapsiyor: mac gunu sorulan soru
                 * "2026-K-0042 nereden girdi" degil, "Sukru Agaoglu nereden
                 * girdi". Kart numarasi ekranda, isim akilda.
                 */
                TextColumn::make('akreditasyon.kart_no')
                    ->label('Kart no')
                    ->placeholder('—')
                    ->description(fn (GecisKaydi $record) => $record->akreditasyon?->kullanici?->name)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('akreditasyon', fn (Builder $a) => $a
                            ->where('kart_no', 'ilike', "%{$search}%")
                            ->orWhereHas('kullanici', fn (Builder $k) => $k
                                ->where('name', 'ilike', "%{$search}%")))),

                TextColumn::make('kapi_kodu')
                    ->label('Kapı')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('yon')
                    ->label('Yön')
                    ->formatStateUsing(fn (string $state) => $state === 'cikis' ? 'Çıkış' : 'Giriş'),

                /*
                 * 💀 Renk `basarili() ? success : danger` ile hesaplaniyordu.
                 * `basarili()` md.12 duzeltmesinde UYARILARI da kapsayacak
                 * sekilde genisletildi (turnikede kirmizi ret ekrani cikmasin
                 * diye) -- o gunden beri bu listede "Mukerrer okutma" ve
                 * "Baska kapida" YESIL gorunuyordu. Bes detay ekraninin hepsi
                 * `renk()` kullaniyor ve SARI gosteriyor; ayrisan tek yer
                 * burasiydi, ustelik tehlikeli yonde: uyari temiz gecis gibi
                 * okunuyordu. Tek tanim enumda.
                 */
                TextColumn::make('sonuc')
                    ->label('Sonuç')
                    ->badge()
                    ->color(fn (GecisSonucu $state) => $state->renk())
                    ->formatStateUsing(fn (GecisSonucu $state) => $state->etiket()),

                TextColumn::make('bolge')
                    ->label('Bölge')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('sebep')
                    ->label('Not')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            /*
             * 🔑 Süzgeçler TABLONUN ÜSTÜNDE (Akreditasyonlar'daki gerekçenin
             * aynısı, orada bir kat daha geçerli): maç günü bu ekran açık
             * duruyor ve sürekli süzülüyor -- "şu kapıda ne oluyor", "kimler
             * geri çevrildi". Her seferinde huniye tıklamak fazladan adım.
             */
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('sonuc')
                    ->label('Sonuç')
                    ->multiple()
                    ->options(fn () => collect(GecisSonucu::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->etiket()])->all()),

                /*
                 * 🪤 Süzgeç kapı KAYDI üzerinden kuruluyor, `kapi_kodu`
                 * metniyle değil: kod kapı düzenlenirken değişebilir, eski
                 * kayıtlarda yazılı kalır. Silinmiş kapı da listede durur --
                 * dünkü maçın kayıtları o kapıya bağlı.
                 */
                SelectFilter::make('kapi_istemcisi_id')
                    ->label('Kapı')
                    ->multiple()
                    ->relationship('kapiIstemcisi', 'ad')
                    ->preload(),

                SelectFilter::make('yon')
                    ->label('Yön')
                    ->options(['giris' => 'Giriş', 'cikis' => 'Çıkış']),

                Filter::make('basarisiz')
                    ->label('Yalnızca reddedilenler')
                    /*
                     * 🪤 "Reddedilen" = GEÇEMEYEN. `!= izinli` yazmak uyarı
                     * sonuçlarını (mükerrer okutma, başka kapıda) da reddedilmiş
                     * gösteriyordu; oysa turnike onları GEÇİRİYOR. Ayrımın tek
                     * tanımı enumda.
                     */
                    ->query(fn (Builder $query) => $query->whereNotIn(
                        'sonuc', array_map(fn (GecisSonucu $s) => $s->value, GecisSonucu::basarililar()),
                    )),

                Filter::make('bugun')
                    ->label('Bugün')
                    ->query(fn (Builder $query) => $query->whereBetween('okundu_at', [
                        today('Europe/Istanbul')->startOfDay(), today('Europe/Istanbul')->endOfDay(),
                    ])),

                /*
                 * Dünkü maçın kayıtlarına bakmak için "Bugün" yetmiyor.
                 * 🪤 `whereDate` sütuna fonksiyon uygular ve indeksi kullanamaz
                 * (Düzeltme listesi md.17); aralık kullanılıyor.
                 */
                Filter::make('tarih')
                    ->label('Tarih aralığı')
                    ->schema([
                        DatePicker::make('baslangic')->label('Başlangıç')->native(false),
                        DatePicker::make('bitis')->label('Bitiş')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $t) => $q
                            ->where('okundu_at', '>=', Carbon::parse($t, 'Europe/Istanbul')->startOfDay()))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $t) => $q
                            ->where('okundu_at', '<=', Carbon::parse($t, 'Europe/Istanbul')->endOfDay())))
                    ->indicateUsing(function (array $data): ?string {
                        $parcalar = array_filter([
                            filled($data['baslangic'] ?? null) ? Carbon::parse($data['baslangic'])->format('d.m.Y') : null,
                            filled($data['bitis'] ?? null) ? Carbon::parse($data['bitis'])->format('d.m.Y') : null,
                        ]);

                        return $parcalar === [] ? null : 'Tarih: '.implode(' — ', $parcalar);
                    }),
            ])
            ->recordActions([])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => app(CsvDisaAktar::class)->akit(
                        $livewire->getFilteredTableQuery()->with(['akreditasyon.kullanici']),
                        'gecis-kayitlari',
                        ['Zaman', 'Kart no', 'Kişi', 'Kapı', 'Yön', 'Sonuç', 'Bölge', 'Not'],
                        fn ($k) => [
                            $k->okundu_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i:s'),
                            $k->akreditasyon?->kart_no,
                            $k->akreditasyon?->kullanici?->name,
                            $k->kapi_kodu,
                            $k->yon === 'cikis' ? 'Çıkış' : 'Giriş',
                            $k->sonuc->etiket(),
                            $k->bolge,
                            $k->sebep,
                        ],
                        // 🔒 Toplu kişisel veri indirme denetime düşer (Düzeltme listesi md.8).
                        olay: 'gecis.disa_aktarildi',
                    )),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Geçiş kaydı yok')
            ->emptyStateDescription('Kapıda kart okutuldukça buraya düşer.');
    }
}
