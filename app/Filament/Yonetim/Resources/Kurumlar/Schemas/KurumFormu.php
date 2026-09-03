<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Schemas;

use App\Enums\CalisanAraligi;
use App\Support\IlIlce;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Kurum künyesi düzenleme formu -- T5 + E6.
 *
 * 🔒 `kurum.yonet` yetkisi ("Kurum bilgisi düzenleme") seeder'da TANIMLIYDI ve
 * KurumPolicy::update() onu kontrol ediyordu, ama hiçbir ekran çağırmıyordu.
 * Aynı desen depoda iki kez düzeltildi (kart.indir, kullanici.yonet):
 * kullanılmayan yetki, kapalı sandığın açık kapısıdır.
 */
class KurumFormu
{
    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            Section::make('Künye')
                ->columns(2)
                ->schema([
                    TextInput::make('resmi_unvan')
                        ->label('Resmî unvan')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

                    TextInput::make('vergi_no')
                        ->label('Vergi / T.C. kimlik numarası')
                        // 🪤 10 hane vergi, 11 hane şahıs işletmesi (T.C. kimlik).
                        // Kamu formundaki kural da ikisini kabul ediyor.
                        ->helperText('10 haneli vergi numarası ya da 11 haneli T.C. kimlik numarası.')
                        ->maxLength(11),

                    TextInput::make('vergi_dairesi')
                        ->label('Vergi dairesi')
                        ->maxLength(120),

                    TextInput::make('eposta')
                        ->label('E-posta')
                        ->email()
                        ->maxLength(200),

                    TextInput::make('telefon')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make('Adres')
                ->columns(2)
                ->schema([
                    Select::make('il')
                        ->label('İl')
                        ->options(fn () => array_combine(IlIlce::iller(), IlIlce::iller()))
                        ->searchable()
                        ->live(),

                    Select::make('ilce')
                        ->label('İlçe')
                        // İlçe listesi seçili ile bağlı; il değişince sıfırlanır.
                        ->options(fn ($get) => filled($get('il'))
                            ? array_combine(IlIlce::ilceler($get('il')), IlIlce::ilceler($get('il')))
                            : [])
                        ->searchable(),

                    TextInput::make('adres')
                        ->label('Açık adres')
                        ->maxLength(300)
                        ->columnSpanFull(),
                ]),

            Section::make('Akreditasyon')
                ->columns(2)
                ->schema([
                    Select::make('calisan_araligi')
                        ->label('Çalışan sayısı')
                        ->options(fn () => collect(CalisanAraligi::cases())
                            ->mapWithKeys(fn (CalisanAraligi $c) => [$c->value => $c->etiket()])->all()),

                    /*
                     * E6: kontenjan listede görünüyordu ve kontenjanDoldu()
                     * kuralı işliyordu, ama sayıyı değiştirecek ekran yoktu.
                     * Başvuru kabulünü doğrudan etkilediği için değişikliği
                     * denetime yazan tek yer bu form olmalı.
                     */
                    TextInput::make('kontenjan')
                        ->label('Kontenjan')
                        ->helperText('Boş bırakılırsa sınırsız.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(999),
                ]),
        ];
    }
}
