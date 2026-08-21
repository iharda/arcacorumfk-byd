<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ulid
 * @property int $basvuru_id
 * @property int $evrak_turu_id
 * @property string $disk
 * @property ?string $yol
 * @property string $orijinal_ad
 * @property string $mime
 * @property int $boyut
 * @property ?string $sha256
 * @property bool $icerik_dogrulandi
 * @property bool $sifreli
 * @property string $dogrulama_durumu
 * @property ?string $dogrulama_notu
 * @property ?Carbon $imha_tarihi
 * @property ?EvrakTuru $turu
 * @property ?Basvuru $basvuru
 */
class Evrak extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'evraklar';

    protected $guarded = ['id'];

    protected $hidden = ['yol'];   // sablonda kazara basilmasin

    protected function casts(): array
    {
        return [
            'boyut' => 'integer',
            'icerik_dogrulandi' => 'boolean',
            'sifreli' => 'boolean',
            'imha_tarihi' => 'date',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Kayıt kalıcı silinince DOSYA da gider. Aksi hâlde kimlik görselleri
         * diskte yetim kalır — hem KVKK ihlali hem de sessiz disk şişmesi.
         * (Soft delete'te dosya KORUNUR: karar geçmişi hâlâ ona bakabilir.)
         *
         * 🪤 TOPLU silme model olaylarını TETİKLEMEZ:
         *   Evrak::where(...)->forceDelete()   → dosya diskte KALIR
         *   Evrak::where(...)->get()->each->forceDelete()  → dosya da silinir
         * Toplu silmen gerekiyorsa ya modelden geç ya da dosyaları elle temizle.
         */
        static::forceDeleted(function (self $evrak) {
            rescue(fn () => Storage::disk($evrak->disk)->delete($evrak->yol), report: false);
        });
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
