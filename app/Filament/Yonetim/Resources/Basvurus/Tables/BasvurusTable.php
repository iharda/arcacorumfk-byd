<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Tables;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Models\Basvuru;
use App\Servisler\CsvDisaAktar;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                 * Basvuran e-postadaki 4 karakterlik numarayla ariyor
                 * (telefonda okunan numara bu). ULID kuyrukta gorunmez.
                 */
                TextColumn::make('basvuru_no')
                    ->label('Başvuru no')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('tur')
                    ->label('Başvuru türü')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (BasvuruTuru $state) => $state->etiket()),

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

                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (BasvuruDurumu $state) => $state->renk())
                    ->formatStateUsing(fn (BasvuruDurumu $state) => $state->etiket())
                    // Durum adları yeni; ne anlama geldikleri fare üstüne gelince.
                    ->tooltip(fn (BasvuruDurumu $state) => $state->aciklama()),

                TextColumn::make('gonderildi_at')
                    ->label('Başvuru tarihi')
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
            ->toolbarActions([])
            ->emptyStateHeading('Kuyrukta başvuru yok')
            ->emptyStateDescription('Yeni başvurular buraya düşer.');
    }
}
