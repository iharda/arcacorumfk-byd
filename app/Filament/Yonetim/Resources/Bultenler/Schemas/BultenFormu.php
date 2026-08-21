<?php

namespace App\Filament\Yonetim\Resources\Bultenler\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

class BultenFormu
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

            RichEditor::make('icerik')
                ->label('Bülten metni')
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h3', 'blockquote', 'undo', 'redo'])
                ->columnSpanFull(),

            FileUpload::make('ekler')
                ->label('Ekler')
                ->helperText('Görsel veya PDF. Ekler yalnızca akredite kullanıcılara açıktır.')
                ->multiple()
                ->disk('icerik')
                ->directory('bulten')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(8192)
                ->maxFiles(10)
                ->columnSpanFull(),

            DateTimePicker::make('yayin_at')
                ->label('Yayın zamanı')
                ->helperText('Boş bırakılırsa yayına aldığınız an kullanılır.')
                ->seconds(false)
                ->timezone('Europe/Istanbul'),
        ];
    }
}
