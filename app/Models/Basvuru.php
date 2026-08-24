<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * @property BasvuruTuru $tur
 * @property BasvuruDurumu $durum
 * @property ?int $kullanici_id hesap ONAY aninda acilir; o ana kadar null
 * @property ?User $kullanici
 * @property ?string $basvuran_ad
 * @property ?string $basvuran_eposta
 * @property ?string $basvuran_telefon
 */
class Basvuru extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'basvurular';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tur' => BasvuruTuru::class,
            'durum' => BasvuruDurumu::class,
            'form_verisi' => 'array',
            'duzeltme_notlari' => 'array',
            'kurum_baslatti' => 'boolean',
            'kurum_teyidi_gerekli' => 'boolean',
            'kurum_teyidi' => 'boolean',
            'kurum_teyidi_at' => 'datetime',
            'gonderildi_at' => 'datetime',
            'incelemeye_alindi_at' => 'datetime',
            'karar_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    /** @return BelongsTo<Kurum, $this> */
    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    public function inceleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inceleyen_id');
    }

    public function kararVeren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'karar_veren_id');
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(Evrak::class, 'basvuru_id');
    }

    public function akreditasyon(): HasOne
    {
        return $this->hasOne(Akreditasyon::class, 'basvuru_id');
    }

    public function davet(): HasOne
    {
        return $this->hasOne(Davet::class, 'basvuru_id');
    }

    /** @return HasMany<BasvuruBileti, $this> */
    public function biletler(): HasMany
    {
        return $this->hasMany(BasvuruBileti::class, 'basvuru_id');
    }

    /** Kullanilabilir durumdaki eksik evrak bileti; yoksa null. */
    public function acikBilet(): ?BasvuruBileti
    {
        return $this->biletler()
            ->whereNull('kullanildi_at')
            ->whereNull('iptal_at')
            ->where('gecerlilik_bitis', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Bildirim hedefi -- TEK kapi. Hesap ONAY aninda acildigi icin (Revizyon
     * md.1) basvurunun buyuk bolumunde kullanici YOKTUR; bildirim ham e-posta
     * adresine gider. Cagiran kod "hesap var mi" diye sormaz.
     */
    public function bildirimHedefi(): object
    {
        if ($this->kullanici !== null) {
            return $this->kullanici;
        }

        $eposta = $this->basvuran_eposta
            ?? throw new RuntimeException('Başvurunun bildirim adresi yok.');

        return Notification::route('mail', $eposta);
    }

    /** Kuyrukta ve ekranlarda gosterilecek ad; hesap acilmamis olabilir. */
    public function basvuranAdi(): string
    {
        return $this->kullanici->name ?? $this->basvuran_ad ?? '—';
    }

    public function basvuranEpostasi(): ?string
    {
        return $this->kullanici->email ?? $this->basvuran_eposta;
    }

    /**
     * Yetkili kuyruğu. Kurum teyidi bekleyen başvuru buraya DÜŞMEZ —
     * önce kurumun "bu kişi çalışanımız" demesi gerekir (Plan v1.0 md.5.2).
     */
    public function scopeKuyrukta(Builder $query): Builder
    {
        return $query
            ->whereIn('durum', array_column(BasvuruDurumu::kuyruk(), 'value'))
            ->where(fn (Builder $alt) => $alt
                ->where('kurum_teyidi_gerekli', false)
                ->orWhereNotNull('kurum_teyidi'));
    }

    /** Kurumun cevabını bekleyenler (kurum panelinde listelenir). */
    public function scopeTeyitBekleyen(Builder $query): Builder
    {
        return $query
            ->where('kurum_teyidi_gerekli', true)
            ->whereNull('kurum_teyidi')
            ->where('durum', BasvuruDurumu::Gonderildi->value);
    }

    public function kurumTeyidiBekliyorMu(): bool
    {
        return $this->kurum_teyidi_gerekli
            && $this->kurum_teyidi === null
            && $this->durum === BasvuruDurumu::Gonderildi;
    }

    /**
     * Durum degistirme -- TEK kapi. Gecerli olmayan gecis sessizce yutulmaz,
     * hata firlatir; boylece bir ekranda unutulan kontrol veriyi bozamaz.
     */
    public function durumaGec(BasvuruDurumu $hedef): void
    {
        if (! $this->durum->gecebilirMi($hedef)) {
            throw new RuntimeException(
                "Geçersiz durum geçişi: {$this->durum->value} → {$hedef->value}"
            );
        }

        $this->durum = $hedef;
    }

    /** Basvuran yalnizca isaretli alanlari duzeltebilir (Plan v1.0 md.4). */
    public function duzeltilebilirAlanlar(): array
    {
        return array_keys($this->duzeltme_notlari ?? []);
    }

    public function eksikEvrakBekleniyorMu(): bool
    {
        return $this->durum === BasvuruDurumu::EksikEvrak;
    }
}
