<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $baslik
 * @property ?string $ozet
 * @property ?string $icerik
 * @property ?string $gorsel_yolu
 * @property bool $yayinda
 * @property ?Carbon $yayin_at
 * @property bool $bildirim_gonderildi
 * @property ?int $olusturan_id
 * @property ?User $olusturan
 */
class Duyuru extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'duyurular';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'yayinda' => 'boolean',
            'yayin_at' => 'datetime',
            'bildirim_gonderildi' => 'boolean',
        ];
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function scopeYayinda(Builder $q): Builder
    {
        return $q->where('yayinda', true)
            ->where(fn ($s) => $s->whereNull('yayin_at')->orWhere('yayin_at', '<=', now()));
    }
}
