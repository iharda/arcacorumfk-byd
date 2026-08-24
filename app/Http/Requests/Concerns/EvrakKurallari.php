<?php

namespace App\Http\Requests\Concerns;

use App\Enums\BasvuruTuru;
use App\Models\EvrakTuru;
use Illuminate\Support\Collection;

/**
 * Başvuru formundaki evrak alanları -- Revizyon md.3.1.
 *
 * Zorunluluk, boyut ve biçim `evrak_turleri` tablosunda tanımlı; kural listesi
 * de oradan türetilir. Böylece panelden bir evrak türü eklendiğinde form da
 * doğrulama da birlikte değişir, iki yerde elle güncelleme gerekmez.
 */
trait EvrakKurallari
{
    /** @var array<string, Collection<int, EvrakTuru>> */
    private array $evrakTuruOnbellegi = [];

    /** @return Collection<int, EvrakTuru> */
    protected function evrakTurleri(BasvuruTuru $tur): Collection
    {
        return $this->evrakTuruOnbellegi[$tur->value] ??= EvrakTuru::turIcin($tur);
    }

    /** @return array<string, array<int, string>> */
    protected function evrakKurallari(BasvuruTuru $tur): array
    {
        // Dizinin kendisi de doğrulanır: `evraklar` yerine dize gönderilirse
        // alan bazlı kurallar hiç çalışmaz, sessizce boş geçerdi.
        $kurallar = ['evraklar' => ['array']];

        foreach ($this->evrakTurleri($tur) as $evrakTuru) {
            $kurallar["evraklar.{$evrakTuru->id}"] = [
                $evrakTuru->zorunlu ? 'required' : 'nullable',
                'file',
                // Boyut sınırı türden; içerik (magic byte) doğrulaması yükleyicide.
                'max:'.$evrakTuru->maks_boyut_kb,
            ];
        }

        return $kurallar;
    }

    /** @return array<string, string> */
    protected function evrakAdlari(BasvuruTuru $tur): array
    {
        return $this->evrakTurleri($tur)
            ->mapWithKeys(fn (EvrakTuru $evrakTuru) => ["evraklar.{$evrakTuru->id}" => $evrakTuru->ad])
            ->all();
    }
}
