<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Evrak extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'evraklar';

    protected $guarded = ['id'];

    protected $hidden = ['yol'];   // sablonda kazara basilmasin

    protected function casts(): array
    {
        return [
            'boyut'             => 'integer',
            'icerik_dogrulandi' => 'boolean',
            'sifreli'           => 'boolean',
            'imha_tarihi'       => 'date',
        ];
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(Basvuru::class, 'basvuru_id');
    }

    public function turu(): BelongsTo
    {
        return $this->belongsTo(EvrakTuru::class, 'evrak_turu_id');
    }

    /**
     * Evrak adresi HER ZAMAN kisa omurlu ve imzali. Public URL YOK (md.11).
     */
    public function gecelikBaglanti(int $dakika = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->yol, now()->addMinutes($dakika));
    }
}
