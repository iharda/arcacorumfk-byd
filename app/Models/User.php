<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Hesap BASVURU ANINDA acilir (Plan v1.0 md.5.5); onaya kadar panelde yalnizca
 * durum + evrak gorunur. Sistem sifre uretmez, kullanici aktivasyon linkiyle
 * kendi belirler.
 */
/*
 * 🪤 Laravel 13'te doldurulabilir alanlar bu ÖZNİTELİKTE tanımlı. Listede
 * olmayan bir alanı User::create()'e verirsen hata almazsın — alan SESSİZCE
 * DÜŞER. kurum_id ve telefon tam olarak böyle kaybolmuştu.
 */
#[Fillable(['name', 'email', 'password', 'telefon', 'adres', 'il', 'ilce', 'kurum_id', 'aktif', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'iki_adimli_gizli', 'iki_adimli_kurtarma_kodlari'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    use HasRoles, Notifiable, SoftDeletes, UlidAnahtari;

    /** Roller -- Spatie. Tek kaynak burasi, ekranlarda dize yazilmaz. */
    public const ROL_SUPER      = 'super';
    public const ROL_YETKILI    = 'yetkili';
    public const ROL_KURUM      = 'kurum';
    public const ROL_BASIN      = 'basin_mensubu';
    public const ROL_ICERIK     = 'icerik_ureticisi';

    protected function casts(): array
    {
        return [
            'email_verified_at'            => 'datetime',
            'password'                     => 'hashed',
            'ayrildi_at'                   => 'datetime',
            'son_giris_at'                 => 'datetime',
            'aktif'                        => 'boolean',
            'iki_adimli_gizli'             => 'encrypted',
            'iki_adimli_kurtarma_kodlari'  => 'encrypted:array',
        ];
    }

    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    public function basvurular(): HasMany
    {
        return $this->hasMany(Basvuru::class, 'kullanici_id');
    }

    public function akreditasyon(): HasOne
    {
        return $this->hasOne(Akreditasyon::class, 'kullanici_id')->latestOfMany();
    }

    /**
     * Panel erisimi. ⚠️ Bu metot OLMAZSA Filament uretimde TUM oturum acmis
     * kullanicilari panele alir. Yetkinin ayrintisi policy'lerde, kapi burada.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->aktif || $this->ayrildi_at !== null) {
            return false;
        }

        return match ($panel->getId()) {
            'yonetim' => $this->hasAnyRole([self::ROL_SUPER, self::ROL_YETKILI]),
            // ⚠️ Akreditasyon ŞARTI YOK: hesap başvuru anında açılır ve kullanıcı
            // onaya kadar panelde yalnızca "başvuru durumu + evrak yükleme"
            // görür (Plan v1.0 md.5.5). Akreditasyona bağlı ekranlar panel
            // içinde tek tek kapatılır.
            'kurum'   => $this->hasRole(self::ROL_KURUM),
            'uye'     => $this->hasAnyRole([self::ROL_BASIN, self::ROL_ICERIK]),
            default   => false,
        };
    }

    /* ---------- Filament 5 yerlesik iki adimli dogrulama ---------- */

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->iki_adimli_gizli;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->iki_adimli_gizli = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->iki_adimli_kurtarma_kodlari;
    }

    /** @param ?array<string> $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->iki_adimli_kurtarma_kodlari = $codes;
        $this->save();
    }
}
