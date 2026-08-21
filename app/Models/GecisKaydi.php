<?php

namespace App\Models;

use App\Enums\GecisSonucu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ?int $akreditasyon_id
 * @property ?int $kapi_istemcisi_id
 * @property ?string $kapi_kodu
 * @property string $yon
 * @property GecisSonucu $sonuc
 * @property ?string $bolge
 * @property ?string $sebep
 * @property ?string $okunan_referans
 * @property ?string $ip
 * @property Carbon $okundu_at
 * @property ?Akreditasyon $akreditasyon
 * @property ?KapiIstemcisi $kapiIstemcisi
 */
/** Yalnizca eklenir -- updated_at yok. */
class GecisKaydi extends Model
{
    protected $table = 'gecis_kayitlari';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sonuc' => GecisSonucu::class,
            'okundu_at' => 'datetime',
        ];
    }

    public function akreditasyon(): BelongsTo
    {
        return $this->belongsTo(Akreditasyon::class, 'akreditasyon_id');
    }

    public function kapiIstemcisi(): BelongsTo
    {
        return $this->belongsTo(KapiIstemcisi::class, 'kapi_istemcisi_id');
    }
}
