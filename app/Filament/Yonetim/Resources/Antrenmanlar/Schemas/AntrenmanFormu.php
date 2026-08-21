<?php

namespace App\Filament\Yonetim\Resources\Antrenmanlar\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AntrenmanFormu
{
    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            TextInput::make('baslik')
                ->label('Başlık')
                ->placeholder('Maç öncesi antrenman')
                ->maxLength(200)
                ->columnSpanFull(),

            DateTimePicker::make('baslangic_at')
                ->label('Başlangıç')
                ->required()
                ->seconds(false)
                ->timezone('Europe/Istanbul'),

            DateTimePicker::make('bitis_at')
                ->label('Bitiş')
                ->seconds(false)
                ->timezone('Europe/Istanbul')
                // Bitiş başlangıçtan önce olamaz; sessizce ters kayıt girmesin.
                ->after('baslangic_at'),

            TextInput::make('yer')
                ->label('Yer')
                ->placeholder('Nazmi Avluca Tesisleri')
                ->maxLength(160),

            Toggle::make('basina_acik')
                ->label('Basına açık')
                ->helperText('Kapalıysa takvimde "kapalı" olarak görünür.')
                ->default(true),

            Textarea::make('not')
                ->label('Not')
                ->placeholder('İlk 15 dakika görüntü alınabilir.')
                ->rows(2)
                ->maxLength(400)
                ->columnSpanFull(),
        ];
    }
}
