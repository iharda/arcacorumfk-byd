<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Ayar extends Model
{
    protected $table = 'ayarlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deger' => 'array'];
    }

    public static function al(string $anahtar, mixed $varsayilan = null): mixed
    {
        $tumu = Cache::rememberForever('byd.ayarlar', fn () => static::pluck('deger', 'anahtar')->all());

        return $tumu[$anahtar] ?? $varsayilan;
    }

    public static function yaz(string $anahtar, mixed $deger): void
    {
        static::updateOrCreate(['anahtar' => $anahtar], ['deger' => $deger]);
        Cache::forget('byd.ayarlar');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('byd.ayarlar'));
        static::deleted(fn () => Cache::forget('byd.ayarlar'));
    }
}
