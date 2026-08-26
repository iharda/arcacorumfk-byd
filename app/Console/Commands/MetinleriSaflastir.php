<?php

namespace App\Console\Commands;

use App\Models\Ayar;
use App\Models\Bulten;
use App\Models\Duyuru;
use App\Servisler\GuvenliHtml;
use Illuminate\Console\Command;

/**
 * Var olan zengin metinleri bir kereye mahsus saflaştırır --
 * Düzeltme listesi md.2.
 *
 * Mutator'lar YALNIZCA yeni yazımlarda çalışır; saflaştırma eklenmeden önce
 * kaydedilmiş duyuru, bülten ve hukuki metinler ham HTML olarak durur.
 * Bu komut onları bir kez geçirir.
 *
 * ⚠️ Değişen kayıtları rapor eder; `--kuru` ile yalnızca listeler.
 */
class MetinleriSaflastir extends Command
{
    protected $signature = 'bys:metinleri-saflastir {--kuru : Yalnızca farkı göster, yazma}';

    protected $description = 'Kayıtlı duyuru, bülten ve hukuki metinleri saflaştırır';

    public function handle(): int
    {
        $kuru = (bool) $this->option('kuru');
        $degisen = 0;

        foreach ([Duyuru::class, Bulten::class] as $model) {
            /** @var class-string<Duyuru|Bulten> $model */
            $model::withTrashed()->whereNotNull('icerik')->chunkById(100, function ($kayitlar) use (&$degisen, $kuru) {
                foreach ($kayitlar as $kayit) {
                    $temiz = GuvenliHtml::temizle($kayit->getRawOriginal('icerik'));

                    if ($temiz === $kayit->getRawOriginal('icerik')) {
                        continue;
                    }

                    $degisen++;
                    $this->line(sprintf('  %s#%d "%s"', class_basename($kayit), $kayit->id, $kayit->baslik));

                    if (! $kuru) {
                        // 🪤 `icerik` mutator'ı zaten saflaştırıyor; ham değeri
                        // vermek yeterli, iki kez temizlemek zararsız.
                        $kayit->forceFill(['icerik' => $temiz])->saveQuietly();
                    }
                }
            });
        }

        foreach (Ayar::query()->where('anahtar', 'like', '%\_metni')->get() as $ayar) {
            if (! is_string($ayar->deger)) {
                continue;
            }

            $temiz = GuvenliHtml::temizle($ayar->deger);

            if ($temiz === $ayar->deger) {
                continue;
            }

            $degisen++;
            $this->line(sprintf('  Ayar "%s"', $ayar->anahtar));

            if (! $kuru) {
                Ayar::yaz($ayar->anahtar, $temiz);
            }
        }

        $this->info($kuru
            ? "Kuru koşu: {$degisen} kayıt değişecekti."
            : "{$degisen} kayıt saflaştırıldı.");

        return self::SUCCESS;
    }
}
