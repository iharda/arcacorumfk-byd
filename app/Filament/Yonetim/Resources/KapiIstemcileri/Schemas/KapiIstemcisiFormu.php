<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri\Schemas;

use App\Models\Ayar;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class KapiIstemcisiFormu
{
    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            TextInput::make('ad')
                ->label('Kapı adı')
                ->placeholder('Kuzey turnike 1')
                ->required()
                ->maxLength(120),

            TextInput::make('kapi_kodu')
                ->label('Kapı kodu')
                ->helperText('Geçiş kayıtlarına bu kod yazılır.')
                ->placeholder('KUZEY-1')
                ->required()
                ->maxLength(30),

            TextInput::make('ip_listesi')
                ->label('İzinli IP adresleri')
                ->helperText('Virgülle ayırın. Tek IP veya aralık: 1.2.3.4, 10.0.0.0/24. Boş bırakılırsa IP kısıtı UYGULANMAZ.')
                ->placeholder('88.12.3.4')
                ->maxLength(500),

            CheckboxList::make('bolgeler')
                ->label('Bu kapının açtığı bölgeler')
                ->helperText('Seçilmezse bölge kontrolü yapılmaz; kart geçerliyse geçiş izinli sayılır.')
                ->options(fn () => (array) Ayar::al('bolgeler', []))
                ->columns(2),

            Toggle::make('aktif')
                ->label('Etkin')
                ->default(true),
        ];
    }
}
