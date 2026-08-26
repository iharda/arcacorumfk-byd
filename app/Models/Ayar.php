<?php

namespace App\Models;

use App\Servisler\GuvenliHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $anahtar
 * @property mixed $deger
 * @property string $grup
 * @property ?string $aciklama
 */
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
        $tumu = Cache::rememberForever('bys.ayarlar', fn () => static::pluck('deger', 'anahtar')->all());

        return $tumu[$anahtar] ?? $varsayilan;
    }

    public static function yaz(string $anahtar, mixed $deger): void
    {
        /*
         * 🔒 Hukuki metinler zengin metin olarak girilir ve kamuya açık
         * sayfada `{!! !!}` ile basılır (Düzeltme listesi md.2). Ayar yazımı
         * TEK KAPI olduğu için saflaştırma burada; panelden mi, komuttan mı
         * geldiği fark etmez.
         */
        if (str_ends_with($anahtar, '_metni') && is_string($deger)) {
            $deger = GuvenliHtml::temizle($deger);
        }

        static::updateOrCreate(['anahtar' => $anahtar], ['deger' => $deger]);
        Cache::forget('bys.ayarlar');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('bys.ayarlar'));
        static::deleted(fn () => Cache::forget('bys.ayarlar'));
    }
}
