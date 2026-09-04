<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Pages;

use App\Filament\Yonetim\Resources\Kullanicilar\KullaniciResource;
use App\Models\User;
use App\Servisler\DenetimYazici;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ListKullanicilar extends ListRecords
{
    protected static string $resource = KullaniciResource::class;

    public function getTitle(): string
    {
        return 'Kullanıcılar';
    }

    /**
     * 🔒 LİSTE super'e özel kalır.
     *
     * Kaynağın `canAccess`i kulüp yetkilisine de açıldı (kurum detayından
     * çalışan künyesine tıklanabilsin diye). O kapı yalnızca TEK BİR KAYDI
     * okumak için; sistemdeki bütün kullanıcıların listesi ayrı bir şeydir ve
     * `kullanici.yonet` ister.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('viewAny', User::class) ?? false, 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            /*
             * Kulüp yetkilisi ELLE eklenir: basın/kurum hesapları başvurudan
             * doğar ama kulübün kendi personeli için bir başvuru yok.
             *
             * 🔑 Parola BURADA BELİRLENMEZ: rastgele atanır ve kişiye şifre
             * kurulum bağlantısı gider. Yetkilinin parolayı bilmesi ve
             * WhatsApp'tan yollaması en sık sızma yolu.
             */
            Action::make('yetkiliEkle')
                ->label('Kulüp yetkilisi ekle')
                ->icon('heroicon-m-user-plus')
                ->visible(fn () => auth()->user()->can('create', User::class))
                ->schema([
                    TextInput::make('name')->label('Ad soyad')->required()->maxLength(120),
                    TextInput::make('email')
                        ->label('E-posta')
                        ->email()
                        ->required()
                        ->maxLength(150)
                        ->unique(table: 'users', column: 'email'),
                    CheckboxList::make('roller')
                        ->label('Roller')
                        ->options([
                            User::ROL_YETKILI => 'Kulüp yetkilisi',
                            User::ROL_SUPER => 'Yönetici',
                        ])
                        ->default([User::ROL_YETKILI])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $kullanici = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => bcrypt(Str::random(40)),
                        'aktif' => true,
                    ]);

                    foreach ($data['roller'] as $rol) {
                        $kullanici->assignRole($rol);
                    }

                    Password::sendResetLink(['email' => $kullanici->email]);

                    app(DenetimYazici::class)->yaz('kullanici.olusturuldu', $kullanici,
                        yeni: ['eposta' => $kullanici->email, 'roller' => $data['roller']]);

                    Notification::make()
                        ->title('Hesap açıldı, şifre kurulum bağlantısı gönderildi.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
