<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Enums\BasvuruTuru;
use App\Enums\CalisanAraligi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $resmi_unvan
 * @property ?string $adres
 * @property ?string $il
 * @property ?string $ilce
 * @property ?string $telefon
 * @property ?string $eposta
 * @property ?string $vergi_dairesi
 * @property ?string $vergi_no
 * @property ?int $calisan_sayisi
 * @property ?CalisanAraligi $calisan_araligi
 * @property ?array $yayin_platformlari
 * @property ?array $sosyal_medya
 * @property string $akreditasyon_durumu
 * @property ?int $kontenjan
 * @property ?bool $teyit_istensin
 * @property ?Carbon $created_at
 * @property ?Carbon $deleted_at
 */
class Kurum extends Model
{
    use HasFactory, SoftDeletes, UlidAnahtari;

    protected $table = 'kurumlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'yayin_platformlari' => 'array',
            'sosyal_medya' => 'array',
            'kontenjan' => 'integer',
            'calisan_sayisi' => 'integer',
            'calisan_araligi' => CalisanAraligi::class,
            'teyit_istensin' => 'boolean',
        ];
    }

    /**
     * Yetkilinin yeniden kullanılabilir kurum kaydı.
     *
     * Aynı e-postayla daha önce başvurup reddedilen yetkili yeniden
     * başvurduğunda her denemede yeni bir kurum satırı açılmaz; akredite
     * OLMAYAN son kaydı güncellenir. Hesap onay anında açıldığı için bağ
     * e-posta üzerinden kurulur.
     *
     * Vergi numarası tekillik kuralı da bu kaydı hariç tutmak zorunda: kendi
     * kurumunun numarası kişinin kendi başvurusunu engellememeli.
     */
    public static function yetkilininOncekiKurumu(?string $eposta): ?self
    {
        if (blank($eposta)) {
            return null;
        }

        return static::query()
            ->whereIn('id', Basvuru::query()
                ->where('tur', BasvuruTuru::Kurum->value)
                ->where('basvuran_eposta', $eposta)
                ->whereNotNull('kurum_id')
                ->pluck('kurum_id'))
            ->where('akreditasyon_durumu', '!=', 'akredite')
            ->latest('id')
            ->first();
    }

    /** @return HasMany<User, $this> */
    public function calisanlar(): HasMany
    {
        return $this->hasMany(User::class, 'kurum_id');
    }

    public function basvurular(): HasMany
    {
        return $this->hasMany(Basvuru::class, 'kurum_id');
    }

    public function akreditasyonlar(): HasMany
    {
        return $this->hasMany(Akreditasyon::class, 'kurum_id');
    }

    public function davetler(): HasMany
    {
        return $this->hasMany(Davet::class, 'kurum_id');
    }

    public function akrediteMi(): bool
    {
        return $this->akreditasyon_durumu === 'akredite';
    }

    /** Kontenjan doldu mu? null kontenjan = sinirsiz. */
    public function kontenjanDoldu(): bool
    {
        if ($this->kontenjan === null) {
            return false;
        }

        return $this->akreditasyonlar()->where('durum', 'aktif')->count() >= $this->kontenjan;
    }
}
