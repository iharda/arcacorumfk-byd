<?php

namespace App\Console\Commands;

use App\Models\Evrak;
use App\Servisler\DenetimYazici;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Evrak imhası -- Plan v1.0 md.11 (KVKK): reddedilen/iptal başvuruların
 * kimlik görselleri süresiz saklanmaz.
 *
 * `imha_tarihi` yükleme anında evrak türündeki `imha_gun` değerinden hesaplanır;
 * yalnızca hassas evrakta doludur. Zamanlayıcı her gece çalışır.
 *
 * ⚠️ Kayıt SİLİNMEZ, yalnızca DOSYA silinir ve iz denetim kaydına düşer:
 * hangi evrakın ne zaman imha edildiği sorulduğunda cevap verebilmeliyiz.
 */
class EvrakImha extends Command
{
    protected $signature = 'bys:evrak-imha {--kuru : Yalnızca listele, silme}';

    protected $description = 'Saklama süresi dolan evrak dosyalarını imha eder';

    public function handle(DenetimYazici $denetim): int
    {
        $kuru = (bool) $this->option('kuru');

        $sorgu = Evrak::withTrashed()
            ->whereNotNull('imha_tarihi')
            // `imha_tarihi` zaten `date` sütunu: karşılaştırma doğrudan indeksten (md.17).
            ->where('imha_tarihi', '<=', today())
            ->whereNotNull('yol');

        $aday = 0;
        $sayac = 0;

        /*
         * 🪤 `->get()` TÜM adayları belleğe alıyordu (Düzeltme listesi md.17).
         * Bir sezonun sonunda binlerce hassas evrak birikir; gece çalışan
         * komut bellek sınırına çarpıp hiç imha yapmadan ölürdü.
         */
        $sorgu->chunkById(200, function ($adaylar) use (&$aday, &$sayac, $kuru, $denetim) {
            foreach ($adaylar as $evrak) {
                $aday++;
                $this->line(($kuru ? '[kuru] ' : '').$evrak->ulid.' · '.$evrak->orijinal_ad);

                if ($kuru) {
                    continue;
                }

                rescue(fn () => Storage::disk($evrak->disk)->delete($evrak->yol), report: false);

                $evrak->forceFill(['yol' => null, 'imha_tarihi' => null])->saveQuietly();

                $denetim->yaz('evrak.imha_edildi', $evrak,
                    yeni: ['orijinal_ad' => $evrak->orijinal_ad],
                    not: 'Saklama süresi doldu',
                    aktorTip: 'sistem');

                $sayac++;
            }
        });

        if ($aday === 0) {
            $this->info('İmha edilecek evrak yok.');

            return self::SUCCESS;
        }

        $this->info($kuru ? "{$aday} evrak imha edilecekti." : "{$sayac} evrak imha edildi.");

        return self::SUCCESS;
    }
}
