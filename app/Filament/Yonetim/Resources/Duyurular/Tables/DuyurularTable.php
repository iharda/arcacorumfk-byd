<?php

namespace App\Filament\Yonetim\Resources\Duyurular\Tables;

use App\Filament\Yonetim\Resources\Duyurular\Schemas\DuyuruFormu;
use App\Models\Duyuru;
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

class DuyurularTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('baslik')->label('Başlık')->searchable()->wrap()->limit(80),

                IconColumn::make('yayinda')->label('Yayında')->boolean(),

                IconColumn::make('bildirim_gonderildi')
                    ->label('Bildirim')
                    ->boolean()
                    ->tooltip('Akredite kullanıcılara e-posta gitti mi?'),

                TextColumn::make('yayin_at')
                    ->label('Yayın')
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('olusturan.name')->label('Ekleyen')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('yayinda')->label('Yayın durumu')
                    ->trueLabel('Yayında')->falseLabel('Taslak'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Duyuru ekle')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema(DuyuruFormu::alanlar())
                    ->mutateDataUsing(fn (array $data) => $data + ['olusturan_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle')->modalWidth(Width::ThreeExtraLarge)->schema(DuyuruFormu::alanlar()),

                Action::make('yayinla')
                    ->label('Yayına al')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    // İlk yayında yüzlerce kişiye e-posta gider: önceden söyle.
                    ->modalDescription(fn (Duyuru $record) => $record->bildirim_gonderildi
                        ? 'Duyuru yeniden yayına alınacak. Bildirim daha önce gönderildiği için tekrar e-posta GİTMEZ.'
                        : 'Duyuru yayına alınacak ve tüm akredite kullanıcılara e-posta gönderilecek.')
                    ->visible(fn (Duyuru $record) => ! $record->yayinda)
                    ->action(function (Duyuru $record) {
                        app(IcerikAkisi::class)->yayinla($record, 'duyuru');
                        Notification::make()->title('Duyuru yayında.')->success()->send();
                    }),

                Action::make('yayindanKaldir')
                    ->label('Yayından kaldır')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Duyuru $record) => $record->yayinda)
                    ->action(function (Duyuru $record) {
                        app(IcerikAkisi::class)->yayindanKaldir($record, 'duyuru');
                        Notification::make()->title('Duyuru yayından kaldırıldı.')->success()->send();
                    }),

                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Duyuru yok')
            ->emptyStateDescription('İlk duyuruyu ekleyin; yayına aldığınızda akredite kullanıcılara e-posta gider.');
    }
}
