<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Tables;

use App\Enums\DegerlendirmePuani;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
use App\Models\Degerlendirme;
use App\Models\User;
use App\Servisler\DenetimYazici;
use App\Support\Telefon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

/**
 * Kullanıcı ve rol yönetimi -- Düzeltme listesi md.6.
 *
 * Her işlem `DenetimYazici`'ya düşer: rol değişikliği ve 2FA sıfırlama
 * sistemdeki en hassas iki olay.
 */
class KullanicilarTable
{
    /** Panelden atanabilecek roller: birey rolleri BAŞVURUDAN doğar. */
    private const YONETILEBILIR_ROLLER = [User::ROL_SUPER, User::ROL_YETKILI];

    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'degerlendirme']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Ad soyad')->searchable()->sortable(),

                TextColumn::make('email')->label('E-posta')->searchable()->copyable(),

                TextColumn::make('roles.name')
                    ->label('Roller')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::rolEtiketi($state)),

                TextColumn::make('telefon')
                    ->label('Telefon')
                    ->formatStateUsing(fn (?string $state) => Telefon::goster($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('iki_adimli_gizli')
                    ->label('2FA')
                    ->boolean()
                    ->getStateUsing(fn (User $record) => $record->iki_adimli_gizli !== null),

                TextColumn::make('son_giris_at')
                    ->label('Son giriş')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Istanbul')
                    ->placeholder('—')
                    ->sortable(),

                IconColumn::make('aktif')->label('Aktif')->boolean(),

                /*
                 * 🔒 Yalnızca kulüp tarafı -- kişi bu puanı hiçbir ekranda
                 * görmez. Bağ e-posta üzerinden (`User::degerlendirme()`):
                 * puan hesap açılmadan önce verilmiş olabilir.
                 */
                TextColumn::make('degerlendirme.puan')
                    ->label('Değerlendirme')
                    ->badge()
                    ->color(fn (?DegerlendirmePuani $state) => $state?->renk() ?? 'gray')
                    ->formatStateUsing(fn (?DegerlendirmePuani $state) => $state
                        ? $state->value.' · '.$state->etiket()
                        : '—')
                    ->placeholder('—')
                    /*
                     * 🪤 Sıralama ALT SORGUYLA: ilişki `lower(users.email)`
                     * üzerinden kurulu, JOIN'i Filament üretemez. `users.email`
                     * projede küçük harfe indirgenmeden saklanıyor, bu yüzden
                     * iki taraf da burada indirgeniyor.
                     */
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        Degerlendirme::query()
                            ->select('puan')
                            ->whereRaw('degerlendirmeler.eposta = lower(users.email)')
                            ->where('hedef_tip', Degerlendirme::HEDEF_KISI)
                            ->limit(1),
                        $direction,
                    ))
                    ->visible(fn () => auth()->user()?->can('degerlendirme.yonet') ?? false),
            ])
            ->filters([
                SelectFilter::make('roller')
                    ->label('Rol')
                    ->multiple()
                    ->options(fn () => Role::pluck('name', 'name')
                        ->map(fn (string $ad) => static::rolEtiketi($ad))->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['values'] ?? []),
                        fn (Builder $q) => $q->whereHas('roles',
                            fn (Builder $r) => $r->whereIn('name', $data['values'])),
                    )),

                Filter::make('pasif')
                    ->label('Yalnızca pasif hesaplar')
                    ->query(fn (Builder $query) => $query->where('aktif', false)),
            ])
            ->recordActions([
                DegerlendirmeEylemi::kisi(),

                Action::make('roller')
                    ->label('Roller')
                    ->icon('heroicon-m-key')
                    ->visible(fn (User $record) => auth()->user()->can('rolYonet', $record))
                    ->schema([
                        CheckboxList::make('roller')
                            ->label('Kulüp rolleri')
                            ->options(fn () => collect(self::YONETILEBILIR_ROLLER)
                                ->mapWithKeys(fn (string $r) => [$r => static::rolEtiketi($r)])->all()),
                    ])
                    ->fillForm(fn (User $record) => [
                        'roller' => $record->roles->pluck('name')
                            ->intersect(self::YONETILEBILIR_ROLLER)->values()->all(),
                    ])
                    ->action(function (User $record, array $data) {
                        /*
                         * 🪤 `syncRoles` KULLANILMAZ: gazetenin sahibi hem
                         * kurum yetkilisi hem basın mensubu olabilir; sync
                         * o rolleri de silerdi. Yalnızca KULÜP rollerine
                         * dokunuyoruz.
                         */
                        $secilen = array_values(array_intersect(
                            $data['roller'] ?? [], self::YONETILEBILIR_ROLLER,
                        ));
                        $eski = $record->roles->pluck('name')
                            ->intersect(self::YONETILEBILIR_ROLLER)->values()->all();

                        foreach (array_diff($eski, $secilen) as $kaldirilan) {
                            $record->removeRole($kaldirilan);
                        }

                        foreach (array_diff($secilen, $eski) as $eklenen) {
                            $record->assignRole($eklenen);
                        }

                        app(DenetimYazici::class)->yaz('kullanici.rol_degisti', $record,
                            eski: ['roller' => $eski], yeni: ['roller' => $secilen]);

                        Notification::make()->title('Roller güncellendi.')->success()->send();
                    }),

                Action::make('ikiAdimliSifirla')
                    ->label('2FA sıfırla')
                    ->icon('heroicon-m-device-phone-mobile')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('İki adımlı doğrulamayı sıfırla')
                    ->modalDescription('Kişi bir sonraki girişinde yeniden kurulum yapacak. Telefonunu kaybeden yetkili için kullanılır.')
                    ->modalSubmitActionLabel('Sıfırla')
                    ->visible(fn (User $record) => auth()->user()->can('ikiAdimliSifirla', $record))
                    ->action(function (User $record) {
                        $record->forceFill([
                            'iki_adimli_gizli' => null,
                            'iki_adimli_kurtarma_kodlari' => null,
                        ])->save();

                        app(DenetimYazici::class)->yaz('kullanici.2fa_sifirlandi', $record);

                        Notification::make()->title('2FA sıfırlandı.')->success()->send();
                    }),

                Action::make('sifreBaglantisi')
                    ->label('Şifre bağlantısı gönder')
                    ->icon('heroicon-m-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Şifre sıfırlama bağlantısı gönder')
                    ->modalSubmitActionLabel('Gönder')
                    ->visible(fn (User $record) => auth()->user()->can('sifreSifirla', $record))
                    ->action(function (User $record) {
                        Password::sendResetLink(['email' => $record->email]);

                        app(DenetimYazici::class)->yaz('kullanici.sifre_baglantisi_gonderildi', $record);

                        Notification::make()->title('Bağlantı gönderildi.')->success()->send();
                    }),

                Action::make('pasifeAl')
                    ->label('Pasife al')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hesabı pasife al')
                    ->modalDescription('Kişi hiçbir panele giremez. Kayıtları ve denetim izi durur; hesap SİLİNMEZ.')
                    ->modalSubmitActionLabel('Pasife al')
                    ->visible(fn (User $record) => auth()->user()->can('pasifeAl', $record))
                    ->action(function (User $record) {
                        $record->forceFill(['aktif' => false])->save();

                        app(DenetimYazici::class)->yaz('kullanici.pasife_alindi', $record);

                        Notification::make()->title('Hesap pasife alındı.')->success()->send();
                    }),

                Action::make('aktifEt')
                    ->label('Aktif et')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('aktifEt', $record))
                    ->action(function (User $record) {
                        $record->forceFill(['aktif' => true])->save();

                        app(DenetimYazici::class)->yaz('kullanici.aktif_edildi', $record);

                        Notification::make()->title('Hesap aktif edildi.')->success()->send();
                    }),
            ]);
    }

    /** Rol adının ekranda görünen karşılığı. */
    public static function rolEtiketi(string $rol): string
    {
        return match ($rol) {
            User::ROL_SUPER => 'Yönetici',
            User::ROL_YETKILI => 'Kulüp yetkilisi',
            User::ROL_KURUM => 'Kurum yetkilisi',
            User::ROL_BASIN => 'Basın mensubu',
            User::ROL_ICERIK => 'İçerik üreticisi',
            default => $rol,
        };
    }
}
