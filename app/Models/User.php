<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Hesap ONAY ANINDA acilir (Revizyon md.3.2): basvurusu onaylanmayan kisinin
 * kullanici kaydi hic dogmaz. Sistem sifre uretmez; kullanici sifresini onay
 * e-postasindaki imzali baglantiyla kendi belirler.
 */
/*
 * 🪤 Laravel 13'te doldurulabilir alanlar bu ÖZNİTELİKTE tanımlı. Listede
 * olmayan bir alanı User::create()'e verirsen hata almazsın — alan SESSİZCE
 * DÜŞER. kurum_id ve telefon tam olarak böyle kaybolmuştu.
 */
#[Fillable(['name', 'email', 'password', 'telefon', 'adres', 'il', 'ilce', 'kurum_id', 'aktif', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'iki_adimli_gizli', 'iki_adimli_kurtarma_kodlari'])]
/**
 * @property int $id
 * @property ?string $ulid
 * @property string $name
 * @property string $email
 * @property ?string $telefon
 * @property ?string $adres
 * @property ?string $il
 * @property ?string $ilce
 * @property ?int $kurum_id
 * @property ?Carbon $ayrildi_at
 * @property bool $aktif
 * @property ?Carbon $son_giris_at
 * @property ?Carbon $email_verified_at
 * @property ?string $iki_adimli_gizli
 * @property ?array $iki_adimli_kurtarma_kodlari
 * @property ?Kurum $kurum
 * @property ?Akreditasyon $akreditasyon
 * @property Collection<int, Akreditasyon> $akreditasyonlar
 * @property Collection<int, Basvuru> $basvurular
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    use HasRoles, Notifiable, SoftDeletes, UlidAnahtari;

    /** Roller -- Spatie. Tek kaynak burasi, ekranlarda dize yazilmaz. */
    public const ROL_SUPER = 'super';

    public const ROL_YETKILI = 'yetkili';

    public const ROL_KURUM = 'kurum';

    public const ROL_BASIN = 'basin_mensubu';

    public const ROL_ICERIK = 'icerik_ureticisi';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ayrildi_at' => 'datetime',
            'son_giris_at' => 'datetime',
            'aktif' => 'boolean',
            'iki_adimli_gizli' => 'encrypted',
            'iki_adimli_kurtarma_kodlari' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<Kurum, $this> */
    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    /** @return HasMany<Basvuru, $this> */
    public function basvurular(): HasMany
    {
        return $this->hasMany(Basvuru::class, 'kullanici_id');
    }

    /**
     * 🪤 Bu ilişki YALNIZCA "en yeni" kaydı verir. Toplu bir işlem (ayrılış,
     * hesap kapatma) yaparken buna bakma — kişinin birden çok akreditasyonu
     * olabilir ve eskisi hâlâ AKTİF durabilir. O iş için akreditasyonlar().
     */
    public function akreditasyon(): HasOne
    {
        return $this->hasOne(Akreditasyon::class, 'kullanici_id')->latestOfMany();
    }

    /**
     * Kişinin bütün akreditasyonları — yeniden başvuranda birden fazla olur.
     *
     * @return HasMany<Akreditasyon, $this>
     */
    public function akreditasyonlar(): HasMany
    {
        return $this->hasMany(Akreditasyon::class, 'kullanici_id');
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
            'kurum' => $this->hasRole(self::ROL_KURUM),
            /*
             * ⚠️ Akreditasyon ŞARTI VAR (Revizyon md.3.5). Hesap onay anında
             * açılır, rol ve akreditasyon da o işlemde doğar; ikisinden biri
             * yoksa hesap ya elle açılmış ya veri taşımasından kalmıştır.
             * Onaysız kişinin panele girmesi bu satırla kaynağında biter.
             */
            'uye' => $this->hasAnyRole([self::ROL_BASIN, self::ROL_ICERIK])
                && $this->akreditasyonlar()->exists(),
            default => false,
        };
    }

    /** Kullanıcının rolüne göre gireceği panelin yolu. */
    public function panelYolu(): string
    {
        return match (true) {
            $this->hasAnyRole([self::ROL_SUPER, self::ROL_YETKILI]) => '/yonetim',
            $this->hasRole(self::ROL_KURUM) => '/kurum',
            default => '/panel',
        };
    }

    /**
     * GERÇEKTEN girebildiği paneller: yol => ad.
     *
     * 🔑 Tek giriş kapısı (`/giris`) buna bakar. `panelYolu()` yalnızca role
     * bakar; bu metot `canAccessPanel()`ten geçirir. Böylece akreditasyonu
     * olmayan ya da ayrılmış kullanıcı, girişten sonra 403 yiyeceği bir panele
     * yollanmaz — hiç panel çıkmazsa giriş baştan reddedilir.
     *
     * Bir kişi hem kurum yetkilisi hem basın mensubu olabilir (gazete sahibi
     * aynı zamanda muhabir): o zaman iki seçenek döner ve panel seçim ekranı
     * gösterilir.
     *
     * @return array<string, string>
     */
    public function erisebildigiPaneller(): array
    {
        $adlar = [
            'yonetim' => 'Yönetim Paneli',
            'kurum' => 'Kurum Paneli',
            'uye' => 'Basın Paneli',
        ];

        $paneller = [];

        foreach (Filament::getPanels() as $panel) {
            $ad = $adlar[$panel->getId()] ?? null;

            if ($ad === null || ! $this->canAccessPanel($panel)) {
                continue;
            }

            $paneller['/'.$panel->getPath()] = $ad;
        }

        return $paneller;
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

    /**
     * @return ?array<string>
     *
     * Sütun metin, cast 'encrypted:array'. Statik analiz sütun tipini görüyor;
     * dönüşü sınırda netleştiriyoruz ki tip sözleşmesi gerçekten tutsun.
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        $kodlar = $this->iki_adimli_kurtarma_kodlari;

        return is_array($kodlar) ? $kodlar : null;
    }

    /** @param ?array<string> $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['iki_adimli_kurtarma_kodlari' => $codes])->save();
    }
}
