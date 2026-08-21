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
    protected $signature = 'byd:evrak-imha {--kuru : Yalnızca listele, silme}';

    protected $description = 'Saklama süresi dolan evrak dosyalarını imha eder';

    public function handle(DenetimYazici $denetim): int
    {
        $kuru = (bool) $this->option('kuru');

        $adaylar = Evrak::withTrashed()
            ->whereNotNull('imha_tarihi')
            ->whereDate('imha_tarihi', '<=', now())
            ->whereNotNull('yol')
            ->get();

        if ($adaylar->isEmpty()) {
            $this->info('İmha edilecek evrak yok.');

            return self::SUCCESS;
        }

        $sayac = 0;

        foreach ($adaylar as $evrak) {
            $this->line(($kuru ? '[kuru] ' : '') . $evrak->ulid . ' · ' . $evrak->orijinal_ad);

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

        $this->info($kuru ? "{$adaylar->count()} evrak imha edilecekti." : "{$sayac} evrak imha edildi.");

        return self::SUCCESS;
    }
}
