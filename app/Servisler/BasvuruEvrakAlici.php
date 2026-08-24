<?php

namespace App\Servisler;

use App\Exceptions\EvrakAlinamadi;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Başvuru formuyla gelen evrakların toplu alınması -- Revizyon md.3.1.
 *
 * Evrak artık ayrı bir adımda değil, başvurunun kendisiyle birlikte gelir.
 * Başvuru tek bir veritabanı işlemine sarıldığı için burada iki şeye dikkat
 * edilir:
 *
 * 💣 `EvrakYukleyici` dosyayı diske KENDİ işleminden ÖNCE yazar. Dıştaki işlem
 *    geri sararsa veritabanı temizlenir ama dosya diskte KALIR. Bu sınıf
 *    yazdığı her dosyanın yolunu tutar; çağıran başarısızlıkta
 *    `yazilanlariSil()` diyerek yetim dosya bırakmaz.
 *
 * 🔑 Kabul edilecek evrak türleri BAŞVURUNUN TÜRÜNDEN gelir. Form alanı
 *    uydurulmuş bir id taşısa bile başka türe ait bir evrak bu başvuruya
 *    yazılamaz.
 */
class BasvuruEvrakAlici
{
    /** @var array<int, array{disk: string, yol: string}> */
    private array $yazilanlar = [];

    public function __construct(private EvrakYukleyici $yukleyici) {}

    /**
     * @param  array<int|string, mixed>  $dosyalar  evrak_turu_id => yüklenen dosya
     * @param  Collection<int, EvrakTuru>  $turler  başvuru türünün evrak türleri
     *
     * @throws EvrakAlinamadi
     */
    public function hepsiniAl(Basvuru $basvuru, array $dosyalar, Collection $turler): void
    {
        $tanimli = $turler->keyBy('id');

        foreach ($dosyalar as $turId => $dosya) {
            if (! $dosya instanceof UploadedFile) {
                continue;
            }

            $tur = $tanimli->get((int) $turId);

            if ($tur === null) {
                throw new EvrakAlinamadi((int) $turId, 'Bu başvuru için geçersiz evrak türü.');
            }

            try {
                $evrak = $this->yukleyici->yukle($basvuru, $tur, $dosya);
            } catch (RuntimeException $e) {
                throw new EvrakAlinamadi((int) $turId, $e->getMessage(), $e);
            }

            $this->yazilanlar[] = ['disk' => $evrak->disk, 'yol' => $evrak->yol];
        }
    }

    /** İşlem geri sardıysa diske yazılanları da geri al. */
    public function yazilanlariSil(): void
    {
        foreach ($this->yazilanlar as $dosya) {
            Storage::disk($dosya['disk'])->delete($dosya['yol']);
        }

        $this->yazilanlar = [];
    }
}
