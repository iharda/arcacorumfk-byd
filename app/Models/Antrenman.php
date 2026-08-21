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
 * @property ?string $baslik
 * @property Carbon $baslangic_at
 * @property ?Carbon $bitis_at
 * @property ?string $yer
 * @property bool $basina_acik
 * @property ?string $not
 * @property bool $yayinda
 * @property ?Carbon $yayin_at
 * @property bool $bildirim_gonderildi
 * @property ?int $olusturan_id
 * @property ?User $olusturan
 */
class Antrenman extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'antrenmanlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'baslangic_at' => 'datetime',
            'bitis_at' => 'datetime',
            'basina_acik' => 'boolean',
            'yayinda' => 'boolean',
            'yayin_at' => 'datetime',
            'bildirim_gonderildi' => 'boolean',
        ];
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function scopeYayinda(Builder $query): Builder
    {
        return $query->where('yayinda', true);
    }

    public function scopeYaklasan(Builder $q): Builder
    {
        return $q->where('baslangic_at', '>=', now())->orderBy('baslangic_at');
    }
}
