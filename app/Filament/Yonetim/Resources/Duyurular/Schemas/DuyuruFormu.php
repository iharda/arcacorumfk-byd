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
                /*
                 * 🔒 `->image()` DEĞİL: o `image/*` demektir ve buna
                 * `image/svg+xml` DAHİLDİR (Düzeltme listesi md.3). SVG bir
                 * belge biçimidir, içine `<script>` konur ve dosya aynı
                 * origin'de `inline` servis edilir. Bülten ekindeki beyaz
                 * listenin aynısı.
                 */
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk('icerik')
                ->directory('duyuru')
                ->maxSize(4096)
                ->columnSpanFull(),

            FileUpload::make('video_yolu')
                ->label('Video')
                /*
                 * 🔒 Bültendeki beyaz listenin aynısı: yalnızca mp4 ve webm.
                 * İkisi de her tarayıcıda oynar ve `EvrakController` bu iki
                 * MIME'ı `inline` servis edebilenler arasında sayıyor --
                 * listeye girmeyen bir biçim yüklenirse dosya 404 döner.
                 */
                ->acceptedFileTypes(['video/mp4', 'video/webm'])
                ->disk('icerik')
                ->directory('duyuru')
                // 💣 Zincirin en düşüğü kazanır: Livewire 64 MB, php-fpm
                // upload_max_filesize 64 MB. Bkz. config/livewire.php.
                ->maxSize(61440)
                ->columnSpanFull(),

            DateTimePicker::make('yayin_at')
                ->label('Yayın zamanı')
                ->helperText('Boş bırakılırsa yayına aldığınız an kullanılır.')
                ->seconds(false)
                ->timezone('Europe/Istanbul'),
        ];
    }
}
