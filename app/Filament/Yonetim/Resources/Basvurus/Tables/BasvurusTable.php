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
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
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
use Illuminate\Support\Collection;
use Throwable;

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
                        ->mapWithKeys(fn ($d) => [$d->value => $d->etiket()])->all()),
                /*
                     * 🔑 VARSAYILAN SÜZGEÇ YOK (Cüneyt Bey revizyonu 05.09.2026).
                     * Liste eskiden yalnızca KUYRUKTAKİLERİ gösteriyordu; karara
                     * bağlanmış başvurular süzgeç elle temizlenmedikçe hiç
                     * görünmüyordu. "Kurumlarda var, başvurularda yok" tablosunun
                     * yarısı buydu. Artık her zaman tüm veriler gelir; yetkili
                     * daraltmak isterse kendisi seçer.
                     */

                SelectFilter::make('tur')
                    ->label('Tür')
                    ->multiple()
                    ->options(fn () => collect(BasvuruTuru::cases())
                        ->mapWithKeys(fn ($t) => [$t->value => $t->etiket()])->all()),

                /*
                 * 🔑 KURUM TEYİDİ SÜZGECİ (Tutarsızlık incelemesi M3).
                 * Teyit bekleyen başvuru `scopeKuyrukta()` dışında kalır, yani
                 * varsayılan "Durum" süzgeci onu getirse bile yetkili neden
                 * beklediğini göremiyordu. "Bekliyor" seçeneği tam olarak
                 * "kurumdan yanıt gelmediği için duran" başvuruları verir.
                 *
                 * ⚠️ `kurum_teyidi` ÜÇ DEĞERLİ (null/true/false) ve üstüne bir de
                 * `kurum_teyidi_gerekli` var; bu yüzden SelectFilter'ın kendi
                 * sütun eşlemesi değil elle yazılmış sorgu kullanılıyor.
                 */
                SelectFilter::make('kurum_teyidi')
                    ->label('Kurum teyidi')
                    ->options([
                        'bekliyor' => 'Bekliyor',
                        'verildi' => 'Verildi',
                        'reddedildi' => 'Reddedildi',
                        'gerekmez' => 'Gerekmez',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'bekliyor' => $query->where('kurum_teyidi_gerekli', true)->whereNull('kurum_teyidi'),
                        'verildi' => $query->where('kurum_teyidi', true),
                        'reddedildi' => $query->where('kurum_teyidi', false),
                        'gerekmez' => $query->where('kurum_teyidi_gerekli', false),
                        default => $query,
                    }),

                /*
                 * 🔴 M4.3: "Şu gazeteden kaç kişi başvurmuş?" sorusu bugüne
                 * kadar cevaplanamıyordu -- kurum yalnızca ARAMA kutusundan
                 * geçiyordu, süzgeci yoktu. Akreditasyonlar ekranındaki
                 * kalıbın aynısı (relationship + searchable + preload).
                 */
                SelectFilter::make('kurum_id')
                    ->label('Kurum')
                    ->relationship('kurum', 'resmi_unvan')
                    ->searchable()
                    ->preload(),

                // 🟠 M4.3: CSV çıktısı bugün ya hepsi ya hiç.
                Filter::make('tarih')
                    ->label('Gönderim tarihi')
                    ->schema([
                        DatePicker::make('baslangic')->label('Başlangıç')->native(false),
                        DatePicker::make('bitis')->label('Bitiş')->native(false),
                    ])
                    ->columns(2)
                    /*
                     * 🪤 `whereDate` sütuna fonksiyon uygular ve indeksi
                     * kullanamaz; sınırlar aralık olarak veriliyor. Bitiş günü
                     * DAHİL olmalı -- kullanıcı "31'ine kadar" derken 31'i de
                     * kastediyor. (Akreditasyonlar tablosundaki kalıbın aynısı.)
                     */
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['baslangic'] ?? null,
                            fn (Builder $q, $t) => $q->where('gonderildi_at', '>=', Carbon::parse($t)->startOfDay()))
                        ->when($data['bitis'] ?? null,
                            fn (Builder $q, $t) => $q->where('gonderildi_at', '<=', Carbon::parse($t)->endOfDay()))),

                /*
                 * 🟠 M4.3: Genel bakış "en oldest bekleyen 14 gün" diyor ama
                 * listede karşılığı yoktu. Yalnızca KUYRUKTAKİ başvuru sayılır:
                 * karara bağlanmış başvurunun bekleme süresi bir şey anlatmaz
                 * (BasvurusTable'daki `bekleyenSure()` sütunuyla aynı kural).
                 */
                SelectFilter::make('bekleme')
                    ->label('Bekleme süresi')
                    ->options([
                        '7' => '7 günden fazla',
                        '14' => '14 günden fazla',
                        '30' => '30 günden fazla',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        /** @var Builder<Basvuru> $query */

                        // Kuyruk tanımı TEK yerde (Basvuru::scopeKuyrukta):
                        // kurum teyidi bekleyen başvuru da kuyrukta sayılmaz.
                        return $query
                            ->kuyrukta()
                            ->whereNotNull('gonderildi_at')
                            ->where('gonderildi_at', '<=', now()->subDays((int) $data['value']));
                    }),

                // 🟠 M4.3: "Bende olan başvurular".
                SelectFilter::make('inceleyen_id')
                    ->label('Sorumlu')
                    ->relationship('inceleyen', 'name')
                    ->searchable()
                    ->preload(),
            ])
            /*
             * 🔑 M4.4: süzgeçler TABLONUN ÜSTÜNDE ve OTURUMDA KALICI.
             * Akreditasyonlar ekranı bu kaliteyi zaten sunuyordu; başvuru
             * kuyruğu günde onlarca kez süzülen liste olduğu hâlde her
             * seferinde huniye tıklamak gerekiyordu. `persistFiltersInSession`
             * "Maç günü görünümü" gibi kayıtlı kümelerin ilk adımı.
             */
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
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
            /*
             * 🔑 SATIR AKSİYONLARI AÇILIR MENÜDE (Cüneyt Bey revizyonu
             * 05.09.2026). Liste eskiden hiç aksiyon taşımıyordu: her iş için
             * önce inceleme ekranını açmak gerekiyordu. Düğmeleri satıra
             * yaymak yerine tek menüde toplandı -- satırın kendisi zaten
             * detayı açıyor, aksiyonlar onu bastırmamalı.
             *
             * ⚠️ Onay ve red burada YOK: evrakı görmeden verilecek kararlar
             * değil. Listeden yapılabilenler incelemeye alma ve kararı geri
             * alma -- ikisi de belgeye bakmayı gerektirmiyor.
             */
            ->recordActions([
                ActionGroup::make([
                    Action::make('incele')
                        ->label('İnceleme ekranını aç')
                        ->icon('heroicon-m-magnifying-glass')
                        ->url(fn (Basvuru $record) => BasvuruResource::getUrl('inceleme', ['record' => $record])),

                    Action::make('incelemeyeAl')
                        ->label('İncelemeye al')
                        ->icon('heroicon-m-eye')
                        ->visible(fn () => auth()->user()->can('basvuru.incele'))
                        ->disabled(fn (Basvuru $record) => ! auth()->user()->can('incele', $record))
                        ->action(fn (Basvuru $record) => self::calistir(
                            fn () => app(BasvuruAkisi::class)->incelemeyeAl($record),
                            'Başvuru incelemenize alındı.',
                        )),

                    Action::make('karariGeriAl')
                        ->label('Kararı geri al')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn (Basvuru $record) => auth()->user()->can('karariGeriAl', $record))
                        ->schema([
                            Textarea::make('gerekce')->label('Gerekçe')->required()->rows(3)->maxLength(500),
                        ])
                        ->modalDescription('Başvuru "İnceleniyor" durumuna döner. Üretilmiş kart İPTAL '
                            .'EDİLİR, akreditasyon rolü geri alınır; kurumsal başvuruda kurumun '
                            .'akreditasyonu da düşer.')
                        ->modalSubmitActionLabel('Kararı geri al')
                        ->action(fn (Basvuru $record, array $data) => self::calistir(
                            fn () => app(BasvuruAkisi::class)->karariGeriAl($record, $data['gerekce']),
                            'Karar geri alındı.',
                        )),
                ])
                    ->label('İşlemler')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
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

    /** Servis hatası bildirime dönsün, ekran 500 vermesin (Akreditasyonlar kalıbı). */
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
