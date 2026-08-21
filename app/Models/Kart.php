<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kart extends Model
{
    use UlidAnahtari;

    protected $table = 'kartlar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'surum'             => 'integer',
            'qr_anahtar_surumu' => 'integer',
            'arsiv'             => 'boolean',
            'uretildi_at'       => 'datetime',
        ];
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
