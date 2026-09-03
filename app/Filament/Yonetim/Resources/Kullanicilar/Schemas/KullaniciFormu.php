<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Schemas;

use App\Filament\Yonetim\Resources\Kullanicilar\Tables\KullanicilarTable;
use App\Support\IlIlce;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Kullanıcı künyesi düzenleme -- T13.
 *
 * ⚠️ E-POSTA BU FORMDA YOK. E-posta giriş kimliğinin kendisi; değiştirmek
 * hesabın kapısını değiştirmek demek. Ayrı bir eylem olarak duruyor
 * ({@see KullanicilarTable}),
 * denetime yazılıyor ve eski adrese bilgilendirme gidiyor.
 *
 * 🪤 TUZAK: User modelinde doldurulabilir alanlar #[Fillable([...])]
 * özniteliğinde. Buraya eklenen bir alan orada YOKSA hata vermeden sessizce
 * düşer -- kurum_id ve telefon tam olarak böyle kaybolmuştu. Aşağıdaki
 * alanların hepsi o listede tanımlı.
 */
class KullaniciFormu
{
    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            TextInput::make('name')
                ->label('Ad soyad')
                ->required()
                ->maxLength(150),

            TextInput::make('telefon')
                ->label('Telefon')
                ->tel()
                ->maxLength(30),

            Select::make('il')
                ->label('İl')
                ->options(fn () => array_combine(IlIlce::iller(), IlIlce::iller()))
                ->searchable()
                ->live(),

            Select::make('ilce')
                ->label('İlçe')
                ->options(fn ($get) => filled($get('il'))
                    ? array_combine(IlIlce::ilceler($get('il')), IlIlce::ilceler($get('il')))
                    : [])
                ->searchable(),

            TextInput::make('adres')
                ->label('Açık adres')
                ->maxLength(300)
                ->columnSpanFull(),
        ];
    }
}
