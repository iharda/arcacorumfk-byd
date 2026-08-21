<?php

namespace App\Filament\Yonetim\Resources\GecisKayitlari\Tables;

use App\Enums\GecisSonucu;
use App\Models\GecisKaydi;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class GecisKayitlariTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['akreditasyon.kullanici', 'kapiIstemcisi']))
            ->defaultSort('okundu_at', 'desc')
            ->deferLoading()          // maç günü tablo büyür; ilk açılış hızlı kalsın
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('okundu_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Istanbul')
                    ->sortable(),

                TextColumn::make('akreditasyon.kart_no')
                    ->label('Kart no')
                    ->placeholder('—')
                    ->searchable()
                    ->description(fn (GecisKaydi $record) => $record->akreditasyon?->kullanici?->name),

                TextColumn::make('kapi_kodu')
                    ->label('Kapı')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('yon')
                    ->label('Yön')
                    ->formatStateUsing(fn (string $state) => $state === 'cikis' ? 'Çıkış' : 'Giriş'),

                TextColumn::make('sonuc')
                    ->label('Sonuç')
                    ->badge()
                    ->color(fn (GecisSonucu $state) => $state->basarili() ? 'success' : 'danger')
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
            ->filters([
                SelectFilter::make('sonuc')
                    ->label('Sonuç')
                    ->multiple()
                    ->options(fn () => collect(GecisSonucu::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->etiket()])->all()),

                Filter::make('basarisiz')
                    ->label('Yalnızca reddedilenler')
                    ->query(fn (Builder $query) => $query->where('sonuc', '!=', GecisSonucu::Izinli->value)),

                Filter::make('bugun')
                    ->label('Bugün')
                    ->query(fn (Builder $query) => $query->whereDate('okundu_at', today())),
            ])
            ->recordActions([])
            ->headerActions([
                Action::make('disaAktar')
                    ->label('CSV indir')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn () => auth()->user()->can('rapor.disaaktar'))
                    ->action(fn ($livewire) => self::csv($livewire->getFilteredTableQuery())),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Geçiş kaydı yok')
            ->emptyStateDescription('Kapıda kart okutuldukça buraya düşer.');
    }

    /** Süzgeçlenmiş kayıtları akış hâlinde indirir — büyük tabloda bellek şişmesin. */
    private static function csv(Builder $sorgu)
    {
        $ad = 'gecis-kayitlari-' . now()->format('Ymd-His') . '.csv';

        return Response::streamDownload(function () use ($sorgu) {
            $cikti = fopen('php://output', 'w');
            fwrite($cikti, "\xEF\xBB\xBF");   // Excel'in Türkçe karakterleri doğru açması için BOM
            fputcsv($cikti, ['Zaman', 'Kart no', 'Kişi', 'Kapı', 'Yön', 'Sonuç', 'Bölge', 'Not']);

            $sorgu->with(['akreditasyon.kullanici'])->chunk(500, function ($satirlar) use ($cikti) {
                foreach ($satirlar as $k) {
                    fputcsv($cikti, [
                        $k->okundu_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i:s'),
                        $k->akreditasyon?->kart_no,
                        $k->akreditasyon?->kullanici?->name,
                        $k->kapi_kodu,
                        $k->yon === 'cikis' ? 'Çıkış' : 'Giriş',
                        $k->sonuc->etiket(),
                        $k->bolge,
                        $k->sebep,
                    ]);
                }
            });

            fclose($cikti);
        }, $ad, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
