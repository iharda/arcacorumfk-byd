<?php

namespace App\Models;

use App\Enums\GecisSonucu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Yalnizca eklenir -- updated_at yok. */
class GecisKaydi extends Model
{
    protected $table = 'gecis_kayitlari';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sonuc'     => GecisSonucu::class,
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
