<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ulid
 * @property int $akreditasyon_id
 * @property int $surum
 * @property string $disk
 * @property ?string $pdf_yolu
 * @property ?string $gorsel_yolu
 * @property ?int $boyut
 * @property int $qr_anahtar_surumu
 * @property bool $arsiv
 * @property ?Carbon $uretildi_at
 * @property ?int $ureten_id
 * @property ?Akreditasyon $akreditasyon
 */
class Kart extends Model
{
    use UlidAnahtari;

    protected $table = 'kartlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'surum' => 'integer',
            'qr_anahtar_surumu' => 'integer',
            'arsiv' => 'boolean',
            'uretildi_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Kart kaydı silinince dosyaları da gitsin (yetim dosya bırakma).
        // ⚠️ Toplu delete() bu olayı TETİKLEMEZ — modelden sil.
        static::deleted(function (self $kart) {
            foreach ([$kart->pdf_yolu, $kart->gorsel_yolu] as $yol) {
                if ($yol) {
                    rescue(fn () => Storage::disk($kart->disk)->delete($yol), report: false);
                }
            }
        });
    }

    public function akreditasyon(): BelongsTo
    {
        return $this->belongsTo(Akreditasyon::class, 'akreditasyon_id');
    }

    public function ureten(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ureten_id');
    }
}
