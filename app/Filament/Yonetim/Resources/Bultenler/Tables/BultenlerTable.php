<?php

namespace App\Filament\Yonetim\Resources\Bultenler\Tables;

use App\Filament\Yonetim\Resources\Bultenler\Schemas\BultenFormu;
use App\Models\Bulten;
use App\Servisler\IcerikAkisi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BultenlerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('baslik')->label('Başlık')->searchable()->wrap()->limit(80),

                TextColumn::make('ekler')
                    ->label('Ek')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn () => null)
                    ->getStateUsing(fn (Bulten $record) => count($record->ekler ?? []) ?: null)
                    ->placeholder('—'),

                IconColumn::make('yayinda')->label('Yayında')->boolean(),
                IconColumn::make('bildirim_gonderildi')->label('Bildirim')->boolean(),

                TextColumn::make('yayin_at')
                    ->label('Yayın')
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('yayinda')->label('Yayın durumu')
                    ->trueLabel('Yayında')->falseLabel('Taslak'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Bülten ekle')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema(BultenFormu::alanlar())
                    ->mutateDataUsing(fn (array $data) => $data + ['olusturan_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle')->modalWidth(Width::ThreeExtraLarge)->schema(BultenFormu::alanlar()),

                Action::make('yayinla')
                    ->label('Yayına al')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Bulten $record) => $record->bildirim_gonderildi
                        ? 'Bülten yeniden yayına alınacak; tekrar e-posta GİTMEZ.'
                        : 'Bülten yayına alınacak ve akredite kullanıcılara e-posta gönderilecek.')
                    ->visible(fn (Bulten $record) => ! $record->yayinda)
                    ->action(function (Bulten $record) {
                        app(IcerikAkisi::class)->yayinla($record, 'bulten');
                        Notification::make()->title('Bülten yayında.')->success()->send();
                    }),

                Action::make('yayindanKaldir')
                    ->label('Yayından kaldır')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Bulten $record) => $record->yayinda)
                    ->action(function (Bulten $record) {
                        app(IcerikAkisi::class)->yayindanKaldir($record, 'bulten');
                        Notification::make()->title('Bülten yayından kaldırıldı.')->success()->send();
                    }),

                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Bülten yok')
            ->emptyStateDescription('Basın bültenlerini buraya ekleyin.');
    }
}
