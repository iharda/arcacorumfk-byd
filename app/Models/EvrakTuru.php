<?php

namespace App\Models;

use App\Enums\BasvuruTuru;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kod
 * @property string $ad
 * @property ?string $aciklama
 * @property array $basvuru_turleri
 * @property bool $zorunlu
 * @property ?array $izinli_formatlar
 * @property int $maks_boyut_kb
 * @property bool $hassas
 * @property ?int $imha_gun
 * @property int $sira
 * @property bool $aktif
 */
class EvrakTuru extends Model
{
    protected $table = 'evrak_turleri';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basvuru_turleri' => 'array',
            'izinli_formatlar' => 'array',
            'zorunlu' => 'boolean',
            'hassas' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(Evrak::class, 'evrak_turu_id');
    }

    /** @return Collection<int, static> */
    public static function turIcin(BasvuruTuru $tur)
    {
        return static::query()
            ->where('aktif', true)
            ->whereJsonContains('basvuru_turleri', $tur->value)
            ->orderBy('sira')
            ->get();
    }
}
