<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kurum extends Model
{
    use HasFactory, SoftDeletes, UlidAnahtari;

    protected $table = 'kurumlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'yayin_platformlari' => 'array',
            'sosyal_medya'       => 'array',
            'kontenjan'          => 'integer',
            'calisan_sayisi'     => 'integer',
            'teyit_istensin'     => 'boolean',
        ];
    }

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
