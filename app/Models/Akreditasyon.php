<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Enums\AkreditasyonDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $kart_no
 * @property int $yil
 * @property string $tur_kodu
 * @property int $sira
 * @property int $kullanici_id
 * @property int $basvuru_id
 * @property ?int $kurum_id
 * @property AkreditasyonDurumu $durum
 * @property ?array $bolge_yetkileri
 * @property ?Carbon $gecerlilik_baslangic
 * @property ?Carbon $gecerlilik_bitis
 * @property ?string $sezon
 * @property ?Carbon $askiya_alindi_at
 * @property ?Carbon $iptal_at
 * @property ?string $iptal_nedeni
 * @property ?int $durum_degistiren_id
 * @property ?Carbon $created_at
 * @property ?User $kullanici
 * @property ?Basvuru $basvuru
 * @property ?Kurum $kurum
 * @property ?Kart $guncelKart
 * @property Collection<int, Kart> $kartlar
 */
class Akreditasyon extends Model
{
    use UlidAnahtari;

    protected $table = 'akreditasyonlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'durum' => AkreditasyonDurumu::class,
            'bolge_yetkileri' => 'array',
            'gecerlilik_baslangic' => 'date',
            'gecerlilik_bitis' => 'date',
            'askiya_alindi_at' => 'datetime',
            'iptal_at' => 'datetime',
            'yil' => 'integer',
            'sira' => 'integer',
        ];
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(Basvuru::class, 'basvuru_id');
    }

    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    public function kartlar(): HasMany
    {
        return $this->hasMany(Kart::class, 'akreditasyon_id');
    }

    public function guncelKart(): HasOne
    {
        return $this->hasOne(Kart::class, 'akreditasyon_id')
            ->where('arsiv', false)
            ->latestOfMany('surum');
    }

    public function gecisKayitlari(): HasMany
    {
        return $this->hasMany(GecisKaydi::class, 'akreditasyon_id');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('durum', AkreditasyonDurumu::Aktif->value);
    }

    /**
     * Turnike karari. Durum + (varsa) gecerlilik tarihi birlikte bakilir.
     * ⚠️ Sezon yonetimi Faz 2 ama alanlar dolduruldugunda BU metot zaten uygular.
     */
    public function gecerliMi(?string $bolge = null): bool
    {
        if (! $this->durum->gecebilirMi()) {
            return false;
        }

        $bugun = now()->startOfDay();

        if ($this->gecerlilik_baslangic && $this->gecerlilik_baslangic->gt($bugun)) {
            return false;
        }

        if ($this->gecerlilik_bitis && $this->gecerlilik_bitis->lt($bugun)) {
            return false;
        }

        if ($bolge !== null && filled($this->bolge_yetkileri)) {
            return in_array($bolge, $this->bolge_yetkileri, true);
        }

        return true;
    }
}
