<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Tables;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Models\Basvuru;
use App\Servisler\BasvuruAkisi;
use App\Servisler\CsvDisaAktar;
use App\Support\TopluIslem;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BasvurusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /*
             * 🪤 PARAMETRE ADI ÖNEMLİ: Filament kapanış argümanlarını ADA göre
             * enjekte eder. `fn (Builder $q)` yazarsan eşleşme olmaz, Filament
             * tipten çözmeye çalışıp MODELSİZ bir Builder verir ve tablo
             * "newQueryWithoutRelationships() on null" ile 500 döner.
             * Doğru ad: $query.
             */
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['kurum', 'kullanici', 'inceleyen']))
            // Arama neyi kapsıyor? Kutunun kendisi söylesin.
            ->searchPlaceholder('Başvuru no, kişi veya kurum ara')
            // Bekleyen en eski başvuru en üstte: kuyruk sırası bozulmasın.
            ->defaultSort('gonderildi_at', 'asc')
            ->columns([
                /*
                 * Basvuran e-postadaki numarayla ariyor (2026-BV-0137);
                 * ULID kuyrukta hic gorunmez. Gonderilmemis basvuruda numara
                 * YOKTUR -- numara gonderim aninda veriliyor (T3).
                 */
                TextColumn::make('basvuru_no')
                    ->label('No')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable()
                    ->sortable(['no_yil', 'no_sira']),

                /*
                 * Kurumsal başvuruda kurum ünvanı, bireyselde KİŞİ ADI gösterilir.
                 * 💥 Türe bakmak ŞART: basın mensubu başvurusunda da `kurum_id`
                 * doludur (çalıştığı yer). Yalnızca `kurum?->resmi_unvan ?: $state`
                 * yazınca kuyruktaki her satır kurumun adını gösteriyordu; aynı
                 * gazeteden üç kişi başvurunca yetkili kimin kim olduğunu
                 * ayırt edemiyordu. Kişinin kurumu alt satırda duruyor.
                 */
                /*
                 * 💥 İLİŞKİDEN OKUMA: hesap onay anında açılır (Revizyon md.1),
                 * kuyruktaki başvuruların çoğunda `kullanici` YOKTUR. Sütun
                 * `kullanici.name` iken durum boş geliyor, Filament boş state'i
                 * biçimlendirmeden geçiyor ve kuyrukta AD HİÇ GÖRÜNMÜYORDU.
                 * Ad/e-posta artık başvurunun kendisinden okunur.
                 */
                TextColumn::make('basvuran')
                    ->label('Başvuran')
                    ->state(fn (Basvuru $record) => $record->tur === BasvuruTuru::Kurum
                        ? ($record->kurum?->resmi_unvan ?: $record->basvuranAdi())
                        : $record->basvuranAdi())
                    ->description(fn (Basvuru $record) => $record->tur === BasvuruTuru::Kurum
                        ? $record->basvuranEpostasi()
                        : implode(' · ', array_filter([
                            $record->kurum?->resmi_unvan,
                            $record->basvuranEpostasi(),
                        ])))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where(fn (Builder $alt) => $alt
                            ->where('basvuran_ad', 'ilike', "%{$search}%")
                            ->orWhere('basvuran_eposta', 'ilike', "%{$search}%")
                            ->orWhereHas('kurum', fn (Builder $k) => $k->where('resmi_unvan', 'ilike', "%{$search}%"))
                            ->orWhereHas('kullanici', fn (Builder $k) => $k
                                ->where('name', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%")))),

                TextColumn::make('tur')
                    ->label('Tür')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (BasvuruTuru $state) => $state->etiket()),

                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (BasvuruDurumu $state) => $state->renk())
                    ->formatStateUsing(fn (BasvuruDurumu $state) => $state->etiket())
                    // Durum adları yeni; ne anlama geldikleri fare üstüne gelince.
                    ->tooltip(fn (BasvuruDurumu $state) => $state->aciklama())
                    /*
                     * 🔑 Bekleme süresi durumun ALTINDA (saha notları T4):
                     * Genel bakış "en eski bekleyen 14 gün" diyordu ama
                     * hangisi olduğu listede görünmüyordu; yetkili sıralamayı
                     * gözüyle takip etmek zorunda kalıyordu. Kuyrukta olmayan
                     * başvuruda satır boş kalır -- karara bağlanmış başvurunun
                     * bekleme süresi bir şey anlatmaz.
                     */
                    ->description(fn (Basvuru $record) => $record->bekleyenSure()),

                TextColumn::make('gonderildi_at')
                    ->label('Gönderim')
                    // 🕐 timeZone SABİTLENİR: sunucu UTC, kullanıcı Türkiye saati bekler.
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('—')
                    ->sortable(),

                // Listede sürekli durmasına gerek yok: "bu başvuruyu kim
                // inceledi" nadiren sorulan bir soru. Silmiyoruz -- silinirse
                // cevap yalnız denetim kaydında kalır, orada aramak zahmetli;
                // sütun seçicisinden bir tıkla geri geliyor. (Saha notları T2.)
                TextColumn::make('inceleyen.name')
                    ->label('Sorumlu')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->multiple()
                    ->options(fn () => collect(BasvuruDurumu::cases())
                        ->mapWithKeys(fn ($d) => [$d->value => $d->etiket()])->all())
                    ->default(BasvuruDurumu::degerleri(...BasvuruDurumu::kuyruk())),

                SelectFilter::make('tur')
                    ->label('Tür')
                    ->multiple()
                    ->options(fn () => collect(BasvuruTuru::cases())
                        ->mapWithKeys(fn ($t) => [$t->value => $t->etiket()])->all()),
            ])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV olarak indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => app(CsvDisaAktar::class)->akit(
                        $livewire->getFilteredTableQuery()->with(['kullanici', 'kurum', 'kararVeren']),
                        'basvurular',
                        ['Başvuru no', 'Başvuru türü', 'Başvuran', 'E-posta', 'Kurum', 'Durum',
                            'Başvuru tarihi', 'Karar', 'Karar veren'],
                        fn ($b) => [
                            $b->basvuru_no,
                            $b->tur->etiket(),
                            $b->basvuranAdi(),
                            $b->basvuranEpostasi(),
                            $b->kurum?->resmi_unvan,
                            $b->durum->etiket(),
                            $b->gonderildi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                            $b->karar_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                            $b->kararVeren?->name,
                        ],
                        // 🔒 Toplu kişisel veri indirme denetime düşer (Düzeltme listesi md.8).
                        olay: 'basvuru.disa_aktarildi',
                    )),
            ])
            /*
             * 💥 "Aç" SÜTUNU YOK (Cüneyt Bey revizyonu 03.09.2026):
             * "Biz niye en sona aç diye bir buton koyuyoruz? Başvuranın ismine
             * tıklanınca açılır." Satırın kendisi bağlantı; hem tek tıkla
             * hem de fareyle üzerine gelince adres çubuğunda görünür.
             */
            ->recordUrl(fn (Basvuru $record) => BasvuruResource::getUrl('inceleme', ['record' => $record]))
            ->recordActions([])
            /*
             * 🧷 Kuyruk temizliği (saha notları E4): sezon sonunda kuyrukta
             * cevapsız kalmış başvurular tek tek düşürülüyordu.
             *
             * 🔒 Her satır KENDİ servis çağrısından geçer -- denetim kaydı
             * satır satır yazılsın. Kuyrukta olmayan (karara bağlanmış) satır
             * atlanır; iptal yalnız kuyruktan yapılabilir.
             */
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('topluIptal')
                        ->label('Başvuruları iptal et')
                        ->icon('heroicon-m-no-symbol')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->can('basvuru.karar'))
                        ->schema([
                            Textarea::make('gerekce')->label('İptal gerekçesi')->required()->rows(3)->maxLength(500),
                        ])
                        ->modalHeading('Seçilen başvuruları iptal et')
                        // Yıkıcı ve geri alınamaz: sonucu açıkça yaz (E1).
                        ->modalDescription('Başvurular kuyruktan düşer ve yeniden açılamaz. Başvuranlara bildirim GİTMEZ; iptali kendilerine siz haber vermelisiniz. Karara bağlanmış satırlar atlanır.')
                        ->modalSubmitActionLabel('Başvuruları iptal et')
                        ->action(fn (Collection $records, array $data) => TopluIslem::calistir(
                            $records,
                            '%d başvuru iptal edildi.',
                            fn (Basvuru $b) => app(BasvuruAkisi::class)->iptalEt($b, $data['gerekce']),
                            fn (Basvuru $b) => auth()->user()->can('iptalEt', $b),
                        ))
                        ->deselectRecordsAfterCompletion(),
                ])->label('Seçilenler'),
            ])
            ->emptyStateHeading('Kuyrukta başvuru yok')
            ->emptyStateDescription('Yeni başvurular buraya düşer.');
    }
}
