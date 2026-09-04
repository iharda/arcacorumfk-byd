<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri\Tables;

use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Resources\EvrakTurleri\EvrakTuruResource;
use App\Models\EvrakTuru;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EvrakTurleriTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Satırın kendisi düzenlemeyi açar (S1 kuralı; burada detay yok).
            ->recordUrl(fn (EvrakTuru $record) => EvrakTuruResource::getUrl('duzenle', ['record' => $record]))
            ->defaultSort('sira')
            ->columns([
                TextColumn::make('ad')
                    ->label('Belge')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (EvrakTuru $record) => $record->aciklama),

                TextColumn::make('kod')
                    ->label('Kod')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('basvuru_turleri')
                    ->label('İstendiği başvurular')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => BasvuruTuru::tryFrom($state)?->etiket() ?? $state),

                IconColumn::make('zorunlu')
                    ->label('Zorunlu')
                    ->boolean()
                    // Yürürlük tarihi varsa bunu satırda söyle: "zorunlu ama
                    // ne zamandan beri" sorusu kuyruktakiler için kritik.
                    ->tooltip(fn (EvrakTuru $record) => $record->zorunlu && $record->zorunlu_baslangic
                        ? $record->zorunlu_baslangic->format('d.m.Y').' tarihinden sonraki başvurular için'
                        : null),

                IconColumn::make('hassas')
                    ->label('Şifreli')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('maks_boyut_kb')
                    ->label('En büyük')
                    ->formatStateUsing(fn (int $state) => $state >= 1024
                        ? intdiv($state, 1024).' MB'
                        : $state.' KB')
                    ->toggleable(),

                TextColumn::make('imha_gun')
                    ->label('Saklama')
                    ->formatStateUsing(fn (?int $state) => $state ? $state.' gün' : 'Süresiz')
                    ->placeholder('Süresiz')
                    ->toggleable(),

                TextColumn::make('sira')
                    ->label('Sıra')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('aktif')
                    ->label('Etkin')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('basvuru_turu')
                    ->label('Başvuru türü')
                    ->options(fn () => collect(BasvuruTuru::cases())
                        ->mapWithKeys(fn (BasvuruTuru $t) => [$t->value => $t->etiket()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q) => $q->whereJsonContains('basvuru_turleri', $data['value']),
                    )),

                Filter::make('pasif')
                    ->label('Yalnızca kullanımdan kaldırılanlar')
                    ->query(fn (Builder $query) => $query->where('aktif', false)),
            ])
            /*
             * ⚠️ SATIR EYLEMİ ve TOPLU EYLEM YOK -- özellikle silme yok.
             * Mevcut evraklar bu kaydın adına bakıyor; tür silinirse geçmiş
             * başvurulardaki belgeler isimsiz kalır (bkz. EvrakTuruResource).
             */
            ->recordActions([])
            ->toolbarActions([]);
    }
}
