<?php

namespace App\Models;

use App\Enums\BasvuruTuru;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kod
 * @property string $ad
 * @property ?string $aciklama
 * @property array $basvuru_turleri
 * @property bool $zorunlu
 * @property ?Carbon $zorunlu_baslangic
 * @property ?array $izinli_formatlar
 * @property int $maks_boyut_kb
 * @property bool $hassas
 * @property ?int $imha_gun
 * @property int $sira
 * @property bool $aktif
 */
class EvrakTuru extends Model
{
    protected $table = 'evrak_turleri';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basvuru_turleri' => 'array',
            'izinli_formatlar' => 'array',
            'zorunlu' => 'boolean',
            'zorunlu_baslangic' => 'date',
            'hassas' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /**
     * Bu belge, VERİLEN başvuru için zorunlu mu? -- M7.2 mimari notu.
     *
     * 💀 Düz `zorunlu` bayrağı YOLDAKİ başvuruları da vurur: düzeltme turundan
     * dönen eski bir başvuru "Eksik zorunlu evrak" ile durur ve başvuran o
     * belgeyi YÜKLEYEMEZ -- düzeltme bileti yalnız yetkilinin işaretlediği
     * alanları açar. Çıkmaz sokak.
     *
     * `zorunlu_baslangic` bu yüzden var: kural yalnızca o tarihten sonra
     * AÇILAN başvurulara işler. NULL ise (mevcut türlerin hepsi) her zaman
     * geçerli -- eski davranış birebir korunur.
     */
    public function basvuruIcinZorunluMu(Basvuru $basvuru): bool
    {
        return $this->zorunluMu($basvuru->created_at ?? now());
    }

    /**
     * ŞİMDİ açılan bir başvuru için zorunlu mu? -- form kuralı bunu sorar.
     *
     * 🪤 Form kuralı ile akış kuralı AYNI KAYNAKTAN gelmeli. Form düz
     * `zorunlu` bayrağına baksaydı belge yürürlük tarihinden ÖNCE de zorunlu
     * olurdu: kamuya açık form "İmza sirküleri yüklemelisiniz" derken servis
     * "gerek yok" diyecekti. Uçtan uca test bu ayrışmayı yakaladı.
     */
    public function yeniBasvuruIcinZorunluMu(): bool
    {
        return $this->zorunluMu(now());
    }

    private function zorunluMu(\DateTimeInterface $an): bool
    {
        if (! $this->zorunlu) {
            return false;
        }

        return $this->zorunlu_baslangic === null
            || $this->zorunlu_baslangic->lte($an);
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(Evrak::class, 'evrak_turu_id');
    }

    /** @return Collection<int, static> */
    public static function turIcin(BasvuruTuru $tur)
    {
        return static::query()
            ->where('aktif', true)
            ->whereJsonContains('basvuru_turleri', $tur->value)
            ->orderBy('sira')
            ->get();
    }
}
