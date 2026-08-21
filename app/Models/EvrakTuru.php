<?php

namespace App\Models;

use App\Enums\BasvuruTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvrakTuru extends Model
{
    protected $table = 'evrak_turleri';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basvuru_turleri'  => 'array',
            'izinli_formatlar' => 'array',
            'zorunlu'          => 'boolean',
            'hassas'           => 'boolean',
            'aktif'            => 'boolean',
        ];
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(Evrak::class, 'evrak_turu_id');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, static> */
    public static function turIcin(BasvuruTuru $tur)
    {
        return static::query()
            ->where('aktif', true)
            ->whereJsonContains('basvuru_turleri', $tur->value)
            ->orderBy('sira')
            ->get();
    }
}
