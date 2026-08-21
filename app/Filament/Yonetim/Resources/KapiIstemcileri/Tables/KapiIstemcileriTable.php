<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri\Tables;

use App\Filament\Yonetim\Resources\KapiIstemcileri\Schemas\KapiIstemcisiFormu;
use App\Models\Ayar;
use App\Models\KapiIstemcisi;
use App\Servisler\KapiIstemcisiAkisi;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KapiIstemcileriTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ad')
            ->columns([
                TextColumn::make('ad')->label('Kapı')->searchable()->sortable(),
                TextColumn::make('kapi_kodu')->label('Kod')->badge()->color('gray'),

                TextColumn::make('bolgeler')
                    ->label('Bölgeler')
                    ->badge()
                    ->separator()
                    ->formatStateUsing(fn ($state) => ((array) Ayar::al('bolgeler', []))[$state] ?? $state)
                    ->placeholder('Kısıt yok'),

                TextColumn::make('ip_listesi')
                    ->label('IP kısıtı')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->placeholder('YOK')
                    // IP kısıtı olmayan kapı gözden kaçmasın.
                    ->color(fn ($state) => filled($state) ? 'gray' : 'warning')
                    ->wrap(),

                IconColumn::make('aktif')->label('Etkin')->boolean(),

                TextColumn::make('son_kullanim_at')
                    ->label('Son okutma')
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('Hiç')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('yeniKapi')
                    ->label('Kapı ekle')
                    ->icon('heroicon-m-plus')
                    ->modalWidth(Width::Large)
                    ->schema(KapiIstemcisiFormu::alanlar())
                    ->action(function (array $data) {
                        $sonuc = app(KapiIstemcisiAkisi::class)->olustur($data);
                        self::anahtariGoster($sonuc['anahtar'], $sonuc['istemci']->ad);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Düzenle')
                    ->modalWidth(Width::Large)
                    ->schema(KapiIstemcisiFormu::alanlar())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['ip_listesi'] = is_array($data['ip_listesi'] ?? null)
                            ? implode(', ', $data['ip_listesi'])
                            : $data['ip_listesi'];

                        return $data;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $liste = collect(explode(',', (string) ($data['ip_listesi'] ?? '')))
                            ->map(fn ($p) => trim($p))->filter()->values()->all();
                        $data['ip_listesi'] = $liste ?: null;

                        return $data;
                    }),

                Action::make('anahtarYenile')
                    ->label('Anahtarı yenile')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    // Sonucu ÖNCEDEN söyle: bu cihaz yenileme biter bitmez düşer.
                    ->modalDescription('Eski anahtar ANINDA geçersiz olur; o cihaz yeni anahtar girilene kadar okutma yapamaz.')
                    ->action(function (KapiIstemcisi $record) {
                        $anahtar = app(KapiIstemcisiAkisi::class)->anahtariYenile($record);
                        self::anahtariGoster($anahtar, $record->ad);
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tanımlı kapı yok')
            ->emptyStateDescription('Her turnike veya gişe cihazı için ayrı bir kapı tanımlayın.');
    }

    /**
     * Anahtar BİR KEZ gösterilir. Kalıcı bildirim: görevli kopyalamadan
     * ekrandan kaybolmasın.
     */
    private static function anahtariGoster(string $anahtar, string $kapi): void
    {
        Notification::make()
            ->title($kapi . ' için anahtar üretildi')
            ->body('Bu anahtar YALNIZCA ŞİMDİ gösterilir, sunucuda saklanmaz. Cihaza girin:  ' . $anahtar)
            ->success()
            ->persistent()
            ->send();
    }
}
