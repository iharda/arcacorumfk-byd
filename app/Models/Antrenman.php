<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Antrenman extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'antrenmanlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'baslangic_at'        => 'datetime',
            'bitis_at'            => 'datetime',
            'basina_acik'         => 'boolean',
            'yayinda'             => 'boolean',
            'bildirim_gonderildi' => 'boolean',
        ];
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function scopeYayinda(Builder $q): Builder
    {
        return $q->where('yayinda', true);
    }

    public function scopeYaklasan(Builder $q): Builder
    {
        return $q->where('baslangic_at', '>=', now())->orderBy('baslangic_at');
    }
}
