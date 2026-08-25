<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Servisler\GuvenliHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * 🔒 Zengin metin KAYDETME anında saflaştırılır (Düzeltme listesi md.2).
     * Görünümler `{!! !!}` ile ham basıyor; koruma tek kapıda, burada.
     */
    protected function icerik(): Attribute
    {
        return Attribute::set(fn (?string $deger) => GuvenliHtml::temizle($deger));
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
