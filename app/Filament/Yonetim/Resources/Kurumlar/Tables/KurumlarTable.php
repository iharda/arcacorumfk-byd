<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Tables;

use App\Models\Kurum;
use App\Servisler\DenetimYazici;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KurumlarTable
{
    private const DURUMLAR = [
        'beklemede' => 'Beklemede',
        'akredite'  => 'Akredite',
        'iptal'     => 'İptal',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'akreditasyonlar as aktif_kart_sayisi' => fn (Builder $query) => $query->where('durum', 'aktif'),
            ]))
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
                        'iptal'    => 'danger',
                        default    => 'gray',
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
            ])
            ->recordActions([
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
                        $eski = $record->akreditasyon_durumu;
                        $record->update(['akreditasyon_durumu' => 'iptal']);

                        app(DenetimYazici::class)->yaz('kurum.akreditasyon_kaldirildi', $record,
                            eski: ['akreditasyon_durumu' => $eski],
                            yeni: ['akreditasyon_durumu' => 'iptal'],
                            not: $data['gerekce']);

                        Notification::make()->title('Akreditasyon kaldırıldı.')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Kayıtlı kurum yok')
            ->emptyStateDescription('Kurumlar başvuru formundan oluşur.');
    }
}
