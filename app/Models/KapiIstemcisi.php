<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $ad
 * @property string $kapi_kodu
 * @property string $anahtar_onek
 * @property string $anahtar_hash
 * @property ?array $ip_listesi
 * @property ?array $bolgeler
 * @property bool $aktif
 * @property ?Carbon $son_kullanim_at
 * @property ?string $son_kullanim_ip
 */
class KapiIstemcisi extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'kapi_istemcileri';

    protected $guarded = ['id'];

    protected $hidden = ['anahtar_hash'];

    protected function casts(): array
    {
        return [
            'ip_listesi' => 'array',
            'bolgeler' => 'array',
            'aktif' => 'boolean',
            'son_kullanim_at' => 'datetime',
        ];
    }

    public function gecisKayitlari(): HasMany
    {
        return $this->hasMany(GecisKaydi::class, 'kapi_istemcisi_id');
    }
}
