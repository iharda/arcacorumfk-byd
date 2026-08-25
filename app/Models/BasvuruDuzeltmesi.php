<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Support\DuzeltmeAlanlari;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tek bir düzeltme turu -- ne istendi, ne cevaplandı, ne değişti.
 *
 * @property int $id
 * @property string $ulid
 * @property int $basvuru_id
 * @property int $sira
 * @property array<string, string> $talep_notlari
 * @property ?array<int, array<string, string>> $ek_talepler
 * @property ?string $talep_gerekcesi
 * @property ?int $talep_eden_id
 * @property Carbon $talep_at
 * @property ?string $yanit_aciklama
 * @property ?Carbon $yanit_at
 * @property ?array<string, array{eski: mixed, yeni: mixed}> $degisiklikler
 * @property ?User $talepEden
 * @property Basvuru $basvuru
 */
class BasvuruDuzeltmesi extends Model
{
    use UlidAnahtari;

    protected $table = 'basvuru_duzeltmeleri';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'talep_notlari' => 'array',
            'ek_talepler' => 'array',
            'degisiklikler' => 'array',
            'talep_at' => 'datetime',
            'yanit_at' => 'datetime',
        ];
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(Basvuru::class, 'basvuru_id');
    }

    public function talepEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'talep_eden_id');
    }

    /** Ekranda görünen ad: "Düzeltme talebi 01". */
    public function baslik(): string
    {
        return 'Düzeltme talebi '.str_pad((string) $this->sira, 2, '0', STR_PAD_LEFT);
    }

    public function yanitlandiMi(): bool
    {
        return $this->yanit_at !== null;
    }

    /**
     * İstenen her madde: anahtar, etiket, açıklama ve -- yanıtlandıysa --
     * önceki/sonraki değer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function maddeler(): array
    {
        $basvuru = $this->basvuru;
        $maddeler = [];

        foreach ($this->talep_notlari as $anahtar => $aciklama) {
            $degisim = $this->degisiklikler[$anahtar] ?? null;

            $maddeler[] = [
                'anahtar' => $anahtar,
                'etiket' => DuzeltmeAlanlari::etiket($basvuru, $anahtar),
                'aciklama' => $aciklama,
                'eski' => $degisim['eski'] ?? null,
                'yeni' => $degisim['yeni'] ?? null,
                'degisti' => $degisim !== null,
            ];
        }

        foreach ($this->ek_talepler ?? [] as $ek) {
            $degisim = $this->degisiklikler[$ek['anahtar']] ?? null;

            $maddeler[] = [
                'anahtar' => $ek['anahtar'],
                'etiket' => $ek['etiket'],
                'aciklama' => $ek['aciklama'] ?? '',
                'eski' => $degisim['eski'] ?? null,
                'yeni' => $degisim['yeni'] ?? null,
                'degisti' => $degisim !== null,
                'ek' => true,
            ];
        }

        return $maddeler;
    }
}
