<?php

namespace App\Filament\Yonetim\Resources\DenetimKayitlari\Tables;

use App\Models\DenetimKaydi;
use App\Servisler\CsvDisaAktar;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DenetimKayitlariTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->defaultSort('id', 'desc')
            ->deferLoading()          // kayıt hızla büyür; ilk açılış hafif kalsın
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Istanbul')
                    ->sortable(),

                TextColumn::make('olay')
                    ->label('Olay')
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        str_contains($state, 'basarisiz'), str_contains($state, 'kilitlendi'),
                        str_contains($state, 'iptal'), str_contains($state, 'reddedildi') => 'danger',
                        str_contains($state, 'onaylandi'), str_contains($state, 'giris') => 'success',
                        str_contains($state, 'eksik'), str_contains($state, 'aski') => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('aktor_ad')
                    ->label('Kim')
                    /*
                     * Kaynak `aktor_ad` sütunu, ilişki DEĞİL. Kullanıcı silinse
                     * bile kim olduğu kaybolmasın diye ad zaten metin olarak
                     * yazılıyor; canlı ilişkiye bakmak hem gereksiz sorgu hem de
                     * silinmiş kullanıcıda boş sonuç demek olurdu.
                     */
                    ->getStateUsing(fn (DenetimKaydi $record) => $record->aktor_ad
                        ?: match ($record->aktor_tip) {
                            'sistem' => 'Sistem',
                            'anonim' => 'Anonim',
                            default => 'Bilinmiyor',
                        })
                    ->description(fn (DenetimKaydi $record) => $record->aktor_tip === 'kullanici'
                        ? null
                        : $record->aktor_tip)
                    ->searchable(['aktor_ad']),

                TextColumn::make('kayit_etiketi')
                    ->label('Kayıt')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(40),

                TextColumn::make('ip')->label('IP')->placeholder('—')->toggleable(),

                TextColumn::make('not')->label('Not')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('olay')
                    ->label('Olay')
                    ->multiple()
                    ->options(fn () => DenetimKaydi::query()
                        ->distinct()->orderBy('olay')->pluck('olay', 'olay')->all()),

                SelectFilter::make('aktor_tip')
                    ->label('Aktör')
                    ->options(['kullanici' => 'Kullanıcı', 'sistem' => 'Sistem', 'anonim' => 'Anonim']),

                Filter::make('bugun')
                    ->label('Bugün')
                    ->query(fn (Builder $query) => $query->whereDate('created_at', today())),

                Filter::make('guvenlik')
                    ->label('Yalnızca güvenlik olayları')
                    ->query(fn (Builder $query) => $query->where(fn (Builder $alt) => $alt
                        ->where('olay', 'like', 'oturum.%')
                        ->orWhere('olay', 'like', '%iptal%')
                        ->orWhere('olay', 'like', 'kapi_istemcisi.%')
                        ->orWhere('olay', 'like', 'ayar.%'))),
            ])
            ->recordActions([
                Action::make('ayrinti')
                    ->label('Ayrıntı')
                    ->icon('heroicon-m-eye')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    // Eski → yeni farkı ham JSON olarak; yorumlamadan gösteriyoruz
                    // ki kayıt ne diyorsa o görünsün.
                    ->modalContent(fn (DenetimKaydi $record) => view('filament.yonetim.denetim-ayrinti', [
                        'kayit' => $record,
                    ])),
            ])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => app(CsvDisaAktar::class)->akit(
                        $livewire->getFilteredTableQuery(),
                        'denetim-kaydi',
                        ['Zaman', 'Olay', 'Kim', 'Aktör tipi', 'Kayıt', 'Eski', 'Yeni', 'Not', 'IP'],
                        fn ($k) => [
                            $k->created_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i:s'),
                            $k->olay,
                            $k->aktor_ad,
                            $k->aktor_tip,
                            $k->kayit_etiketi,
                            json_encode($k->eski, JSON_UNESCAPED_UNICODE),
                            json_encode($k->yeni, JSON_UNESCAPED_UNICODE),
                            $k->not,
                            $k->ip,
                        ],
                    )),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Denetim kaydı boş')
            ->emptyStateDescription('Kararlar, durum değişiklikleri ve oturum olayları buraya düşer.');
    }
}
