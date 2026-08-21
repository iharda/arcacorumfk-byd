<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KapiIstemcisi extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'kapi_istemcileri';

    protected $guarded = ['id'];

    protected $hidden = ['anahtar_hash'];

    protected function casts(): array
    {
        return [
            'ip_listesi'      => 'array',
            'bolgeler'        => 'array',
            'aktif'           => 'boolean',
            'son_kullanim_at' => 'datetime',
        ];
    }

    public function gecisKayitlari(): HasMany
    {
        return $this->hasMany(GecisKaydi::class, 'kapi_istemcisi_id');
    }
}
