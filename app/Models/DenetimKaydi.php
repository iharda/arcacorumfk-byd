<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property ?int $aktor_id
 * @property string $aktor_tip
 * @property ?string $aktor_ad
 * @property string $olay
 * @property ?string $kayit_tipi
 * @property ?int $kayit_id
 * @property ?string $kayit_etiketi
 * @property ?array $eski
 * @property ?array $yeni
 * @property ?string $not
 * @property ?string $ip
 * @property ?string $tarayici
 * @property ?Carbon $created_at
 * @property ?User $aktor aktor_id boş olabilir (sistem/anonim olay)
 */
/**
 * Denetim kaydi -- SADECE EKLENIR (Plan v1.0 md.10).
 * Guncelleme ve silme model seviyesinde de kapatildi; bir yerde yanlislikla
 * ->update() cagrilirsa sessizce gecmesin, patlasin.
 */
class DenetimKaydi extends Model
{
    protected $table = 'denetim_kaydi';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'eski' => 'array',
            'yeni' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Denetim kaydı güncellenemez.'));
        static::deleting(fn () => throw new RuntimeException('Denetim kaydı silinemez.'));
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktor_id');
    }
}
