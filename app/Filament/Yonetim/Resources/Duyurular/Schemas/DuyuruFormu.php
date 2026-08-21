<?php

namespace App\Filament\Yonetim\Resources\Duyurular\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class DuyuruFormu
{
    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            TextInput::make('baslik')
                ->label('Başlık')
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),

            Textarea::make('ozet')
                ->label('Özet')
                ->helperText('Listede ve bildirim e-postasında görünür.')
                ->rows(2)
                ->maxLength(400)
                ->columnSpanFull(),

            RichEditor::make('icerik')
                ->label('İçerik')
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h3', 'blockquote', 'undo', 'redo'])
                ->columnSpanFull(),

            FileUpload::make('gorsel_yolu')
                ->label('Görsel')
                ->image()
                ->disk('icerik')
                ->directory('duyuru')
                ->maxSize(4096)
                ->columnSpanFull(),

            DateTimePicker::make('yayin_at')
                ->label('Yayın zamanı')
                ->helperText('Boş bırakılırsa yayına aldığınız an kullanılır.')
                ->seconds(false)
                ->timezone('Europe/Istanbul'),
        ];
    }
}
