<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Toplu islemlerin ortak govdesi -- saha notlari E4.
 *
 * "Sezon sonunda yuzlerce akreditasyonun suresi dolacak ve hepsi tek tek elden
 * gececek." Secim + toplu islem o gecisi dakikalara indiriyor.
 *
 * 🔒 Denetim kaydi SATIR SATIR yazilir: tek bir "toplu islem yapildi" kaydi,
 * altı ay sonra "bu kartı kim askıya aldı" sorusuna cevap vermez. Bu yuzden
 * dongu servis metotlarini TEK TEK cagirir -- her cagri kendi denetim kaydini
 * ve kendi islemini acar.
 *
 * 🪤 Hepsi TEK islemde degil: yuz satirin doksan dokuzu gecip biri patlarsa
 * hepsini geri sarmak yetkiliyi bastan basliyor. Uygun olmayan satir sessizce
 * ATLANIR, hata veren satir sayilir ve ozet bildirimde durur.
 */
class TopluIslem
{
    /**
     * @template TKayit of Model
     *
     * @param  Collection<int, TKayit>  $kayitlar
     * @param  string  $ozet  basarili satir sayisini alan bicim: '%d başvuru iptal edildi.'
     * @param  callable(TKayit): mixed  $is  donus degeri kullanilmaz (is dispatch'i de olabilir)
     * @param  ?callable(TKayit): bool  $uygunMu  null ise her satir uygun
     */
    public static function calistir(
        Collection $kayitlar,
        string $ozet,
        callable $is,
        ?callable $uygunMu = null,
    ): void {
        $basarili = 0;
        $atlanan = 0;
        $hatalar = [];

        foreach ($kayitlar as $kayit) {
            if ($uygunMu !== null && ! $uygunMu($kayit)) {
                $atlanan++;

                continue;
            }

            try {
                $is($kayit);
                $basarili++;
            } catch (Throwable $e) {
                $hatalar[] = $e->getMessage();
            }
        }

        if ($basarili === 0 && $hatalar === []) {
            Notification::make()
                ->title('Uygun satır yok')
                ->body('Seçilen '.$kayitlar->count().' satırın hiçbiri bu işleme uygun durumda değil.')
                ->warning()
                ->send();

            return;
        }

        $govde = array_filter([
            $atlanan > 0 ? $atlanan.' satır atlandı (uygun durumda değil).' : null,
            // Ayni hata yuz kez tekrarlanabilir; benzersizi yeter.
            $hatalar !== [] ? count($hatalar).' satırda hata: '.implode(' ', array_unique($hatalar)) : null,
        ]);

        Notification::make()
            ->title(sprintf($ozet, $basarili))
            ->body(implode(' ', $govde) ?: null)
            ->status($hatalar === [] ? 'success' : 'warning')
            ->send();
    }
}
