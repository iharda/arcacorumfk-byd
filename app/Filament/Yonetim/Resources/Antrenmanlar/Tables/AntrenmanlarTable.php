<?php

namespace App\Filament\Yonetim\Resources\Antrenmanlar\Tables;

use App\Filament\Yonetim\Resources\Antrenmanlar\AntrenmanResource;
use App\Filament\Yonetim\Resources\Antrenmanlar\Schemas\AntrenmanFormu;
use App\Models\Antrenman;
use App\Servisler\IcerikAkisi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AntrenmanlarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Satırın kendisi detayı açar (S1).
            ->recordUrl(fn (Antrenman $record) => AntrenmanResource::getUrl('detay', ['record' => $record]))
            // Yaklaşan antrenman en üstte: yetkili genelde ileriye bakar.
            ->defaultSort('baslangic_at', 'desc')
            ->columns([
                TextColumn::make('baslangic_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->sortable(),

                TextColumn::make('baslik')->label('Başlık')->placeholder('Antrenman')->wrap(),

                TextColumn::make('yer')->label('Yer')->placeholder('—')->toggleable(),

                IconColumn::make('basina_acik')->label('Basına açık')->boolean(),
                IconColumn::make('yayinda')->label('Yayında')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('yayinda')->label('Yayın durumu')
                    ->trueLabel('Yayında')->falseLabel('Taslak'),

                Filter::make('yaklasan')
                    ->label('Yalnızca yaklaşanlar')
                    ->query(fn (Builder $query) => $query->where('baslangic_at', '>=', now())),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Antrenman ekle')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->schema(AntrenmanFormu::alanlar())
                    ->mutateDataUsing(fn (array $data) => $data + ['olusturan_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle')->modalWidth(Width::TwoExtraLarge)->schema(AntrenmanFormu::alanlar()),

                Action::make('yayinla')
                    ->label('Yayına al')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Antrenman $record) => $record->bildirim_gonderildi
                        ? 'Takvim kaydı yeniden yayına alınacak; tekrar e-posta GİTMEZ.'
                        : 'Yayına alınacak ve akredite kullanıcılara e-posta gönderilecek.')
                    ->visible(fn (Antrenman $record) => ! $record->yayinda)
                    ->action(function (Antrenman $record) {
                        app(IcerikAkisi::class)->yayinla($record, 'antrenman');
                        Notification::make()->title('Takvim kaydı yayında.')->success()->send();
                    }),

                Action::make('yayindanKaldir')
                    ->label('Yayından kaldır')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Antrenman $record) => $record->yayinda)
                    ->action(function (Antrenman $record) {
                        app(IcerikAkisi::class)->yayindanKaldir($record, 'antrenman');
                        Notification::make()->title('Yayından kaldırıldı.')->success()->send();
                    }),

                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Takvimde kayıt yok')
            ->emptyStateDescription('Basına açık antrenmanları buraya ekleyin.');
    }
}
