<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Davet extends Model
{
    use UlidAnahtari;

    protected $table = 'davetler';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'gecerlilik_bitis' => 'datetime',
            'kullanildi_at'    => 'datetime',
            'iptal_at'         => 'datetime',
        ];
    }

    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(Basvuru::class, 'basvuru_id');
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
