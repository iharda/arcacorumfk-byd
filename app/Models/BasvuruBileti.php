<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eksik evrak duzeltme bileti -- Revizyon md.2.2.
 *
 * 🔒 Ham token HICBIR ZAMAN saklanmaz; yalnizca sha256 hash'i tutulur ve
 * e-postayla bir kez gonderilir. Bilet TEK basvuruya baglidir; baska bir
 * basvuruya erisim vermez.
 *
 * @property int $id
 * @property string $ulid
 * @property int $basvuru_id
 * @property ?int $olusturan_id
 * @property string $token_hash
 * @property string $amac
 * @property Carbon $gecerlilik_bitis
 * @property ?Carbon $kullanildi_at
 * @property ?Carbon $iptal_at
 * @property int $gonderim_sayisi
 * @property ?Basvuru $basvuru
 */
class BasvuruBileti extends Model
{
    use UlidAnahtari;

    protected $table = 'basvuru_biletleri';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'gecerlilik_bitis' => 'datetime',
            'kullanildi_at' => 'datetime',
            'iptal_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Basvuru, $this> */
    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(Basvuru::class, 'basvuru_id');
    }

    /** @return BelongsTo<User, $this> */
    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function kullanilabilirMi(): bool
    {
        return $this->kullanildi_at === null
            && $this->iptal_at === null
            && $this->gecerlilik_bitis->isFuture();
    }

    /** Ham token HICBIR ZAMAN saklanmaz; yalnizca hash karsilastirilir. */
    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
