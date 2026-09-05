<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Enums\DuzeltmeTuru;
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
 * @property DuzeltmeTuru $tur
 * @property array<string, string> $talep_notlari
 * @property ?array<int, array<string, string>> $ek_talepler
 * @property ?string $talep_gerekcesi
 * @property ?int $talep_eden_id
 * @property Carbon $talep_at
 * @property ?Carbon $son_tarih
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
            'tur' => DuzeltmeTuru::class,
            'talep_notlari' => 'array',
            'ek_talepler' => 'array',
            'degisiklikler' => 'array',
            'talep_at' => 'datetime',
            'son_tarih' => 'date',
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

    /** Ekranda görünen ad: "Düzeltme talebi 01" / "Belge talebi 03". */
    public function baslik(): string
    {
        return $this->tur->etiket().' '.str_pad((string) $this->sira, 2, '0', STR_PAD_LEFT);
    }

    public function yanitlandiMi(): bool
    {
        return $this->yanit_at !== null;
    }

    /** Karar SONRASI açılmış, başvurunun durumuna dokunmayan tur. */
    public function belgeTalebiMi(): bool
    {
        return $this->tur === DuzeltmeTuru::BelgeTalebi;
    }

    /**
     * Süre doldu ama cevap gelmedi.
     *
     * ⚠️ Bunun HİÇBİR otomatik sonucu yok: kart askıya alınmaz, erişim
     * kesilmez. Yalnızca panoda ve akreditasyon detayında görünür; ne
     * yapılacağına yetkili karar verir.
     */
    public function suresiGectiMi(): bool
    {
        return $this->yanit_at === null
            && $this->son_tarih !== null
            && $this->son_tarih->isPast();
    }

    /** Bugünden son tarihe kaç gün kaldı? Geçtiyse negatif, tarih yoksa null. */
    public function kalanGun(): ?int
    {
        return $this->son_tarih === null
            ? null
            : (int) now()->timezone('Europe/Istanbul')->startOfDay()
                ->diffInDays($this->son_tarih->timezone('Europe/Istanbul')->startOfDay(), false);
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
            /*
             * 💀 EK TALEPLER İKİ KEZ ÇİZİLİYORDU (İbrahim Bey, 05.09.2026).
             * `talep_notlari` ek talepleri de TAŞIR -- taşımak zorunda:
             * düzeltme formu hangi kutuları açacağını `duzeltilebilirAlanlar()`
             * üzerinden oradan okuyor. Ama aşağıdaki döngü aynı kalemleri
             * `ek_talepler`den bir kez daha ekliyordu; ekranda "yayın
             * sözleşmesi" iki satır görünüyordu.
             *
             * 🔑 Ek talep AŞAĞIDA çizilir: etiketi ve tipi orada duruyor,
             * burada yalnızca ham anahtar (`ek:1`) var.
             */
            if (DuzeltmeAlanlari::ekMi($anahtar)) {
                continue;
            }

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
