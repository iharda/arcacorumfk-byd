<?php

namespace App\Models;

use App\Enums\DegerlendirmePuani;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Yetkilinin başvuran hakkındaki 1-5 değerlendirmesi -- briefi md. Bölüm A.
 *
 * 🚫 `UlidAnahtari` KULLANILMAZ: kaydın kendi rotası yok, dışarıya hiç
 * açılmıyor. Ulid, adreste görünen kayıtlar içindir.
 *
 * 🔒 Bu model YALNIZCA yönetim panelinde okunur. Kurum panelinde, üye
 * panelinde, kapı API'sinde ve kartta hiç görünmez.
 *
 * @property string $hedef_tip 'kurum' | 'kisi'
 * @property ?int $kurum_id
 * @property ?int $kullanici_id hesap açılınca bağlanır; puan ondan önce verilmiş olabilir
 * @property ?string $eposta kişi hedefinin KALICI anahtarı, küçük harf
 * @property ?string $hedef_ad
 * @property DegerlendirmePuani $puan
 * @property ?string $not
 * @property ?int $degerlendiren_id
 * @property ?string $degerlendiren_ad
 * @property ?Carbon $updated_at
 */
class Degerlendirme extends Model
{
    public const HEDEF_KURUM = 'kurum';

    public const HEDEF_KISI = 'kisi';

    protected $table = 'degerlendirmeler';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        /*
         * 🔑 Cast enum'a, sütun ham sayı: `where('puan', '<=', 2)` ve
         * `orderBy('puan')` bozulmadan çalışmaya devam eder.
         */
        return ['puan' => DegerlendirmePuani::class];
    }

    /** @return BelongsTo<Kurum, $this> */
    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    /** @return BelongsTo<User, $this> */
    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    /** @return BelongsTo<User, $this> */
    public function degerlendiren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'degerlendiren_id');
    }

    /** Ekranda gösterilecek hedef adı; kayıt silinmiş olsa da dolu kalır. */
    public function hedefAdi(): string
    {
        // `??` zaten null erişimini bastırır; `?->` fazlalık olurdu.
        return $this->hedef_ad
            ?? $this->kurum->resmi_unvan
            ?? $this->kullanici->name
            ?? $this->eposta
            ?? '—';
    }
}
