<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Tables;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
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
            // Bekleyen en eski başvuru en üstte: kuyruk sırası bozulmasın.
            ->defaultSort('gonderildi_at', 'asc')
            ->columns([
                TextColumn::make('tur')
                    ->label('Tür')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (BasvuruTuru $state) => $state->etiket()),

                // Kurumsal başvuruda kurum ünvanı, bireyselde kişi adı gösterilir.
                TextColumn::make('kullanici.name')
                    ->label('Başvuran')
                    ->formatStateUsing(fn ($state, $record) => $record->kurum?->resmi_unvan ?: $state)
                    ->description(fn ($record) => $record->kullanici?->email)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where(fn (Builder $alt) => $alt
                            ->whereHas('kurum', fn (Builder $k) => $k->where('resmi_unvan', 'ilike', "%{$search}%"))
                            ->orWhereHas('kullanici', fn (Builder $k) => $k
                                ->where('name', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%")))),

                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (BasvuruDurumu $state) => $state->renk())
                    ->formatStateUsing(fn (BasvuruDurumu $state) => $state->etiket()),

                TextColumn::make('gonderildi_at')
                    ->label('Gönderim')
                    // 🕐 timeZone SABİTLENİR: sunucu UTC, kullanıcı Türkiye saati bekler.
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('inceleyen.name')
                    ->label('İnceleyen')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->multiple()
                    ->options(fn () => collect(BasvuruDurumu::cases())
                        ->mapWithKeys(fn ($d) => [$d->value => $d->etiket()])->all())
                    ->default(array_map(fn ($d) => $d->value, BasvuruDurumu::kuyruk())),

                SelectFilter::make('tur')
                    ->label('Tür')
                    ->multiple()
                    ->options(fn () => collect(BasvuruTuru::cases())
                        ->mapWithKeys(fn ($t) => [$t->value => $t->etiket()])->all()),
            ])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => app(CsvDisaAktar::class)->akit(
                        $livewire->getFilteredTableQuery()->with(['kullanici', 'kurum', 'kararVeren']),
                        'basvurular',
                        ['Başvuru no', 'Tür', 'Başvuran', 'E-posta', 'Kurum', 'Durum', 'Gönderim', 'Karar', 'Karar veren'],
                        fn ($b) => [
                            $b->ulid,
                            $b->tur->etiket(),
                            $b->kullanici?->name,
                            $b->kullanici?->email,
                            $b->kurum?->resmi_unvan,
                            $b->durum->etiket(),
                            $b->gonderildi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                            $b->karar_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                            $b->kararVeren?->name,
                        ],
                    )),
            ])
            ->recordActions([
                Action::make('inceleme')
                    ->label('Aç')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn ($record) => BasvuruResource::getUrl('inceleme', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Kuyrukta başvuru yok')
            ->emptyStateDescription('Yeni başvurular buraya düşer.');
    }
}
