<?php

namespace App\Servisler;

use App\Models\KapiIstemcisi;
use Illuminate\Support\Str;

/**
 * Turnike/gişe istemcisi anahtarları -- Plan v1.0 md.7, md.11.
 *
 * 🔑 Ham anahtar HİÇBİR YERDE saklanmaz: yalnızca sha256 hash'i ve panelde
 * kaydı bulmaya yarayan 12 karakterlik önek tutulur. Anahtar üretildiği anda
 * BİR KEZ gösterilir; kaybolursa yenilenir (eskisi anında geçersiz olur).
 */
class KapiIstemcisiAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    /** @return array{istemci: KapiIstemcisi, anahtar: string} */
    public function olustur(array $veri): array
    {
        $anahtar = $this->anahtarUret();

        $istemci = KapiIstemcisi::create([
            'ad' => $veri['ad'],
            'kapi_kodu' => $veri['kapi_kodu'],
            'ip_listesi' => $this->ipListesi($veri['ip_listesi'] ?? null),
            'bolgeler' => $veri['bolgeler'] ?? null,
            'aktif' => $veri['aktif'] ?? true,
            'anahtar_onek' => substr($anahtar, 0, 12),
            'anahtar_hash' => hash('sha256', $anahtar),
        ]);

        $this->denetim->yaz('kapi_istemcisi.olusturuldu', $istemci, yeni: [
            'ad' => $istemci->ad, 'kapi_kodu' => $istemci->kapi_kodu,
        ]);

        return ['istemci' => $istemci, 'anahtar' => $anahtar];
    }

    public function anahtariYenile(KapiIstemcisi $istemci): string
    {
        $anahtar = $this->anahtarUret();

        $istemci->update([
            'anahtar_onek' => substr($anahtar, 0, 12),
            'anahtar_hash' => hash('sha256', $anahtar),
        ]);

        $this->denetim->yaz('kapi_istemcisi.anahtar_yenilendi', $istemci,
            not: 'Eski anahtar anında geçersiz oldu.');

        return $anahtar;
    }

    /** Çakışma olmayan, okunabilir bir anahtar. */
    private function anahtarUret(): string
    {
        do {
            // Karışan karakter yok (0/O, 1/l): görevli anahtarı elle girecek.
            $anahtar = 'kapi_'.Str::lower(Str::random(40));
        } while (KapiIstemcisi::where('anahtar_onek', substr($anahtar, 0, 12))->exists());

        return $anahtar;
    }

    /** "1.2.3.4, 10.0.0.0/24" → dizi. Boşsa null (kısıt yok). */
    private function ipListesi(?string $ham): ?array
    {
        $liste = collect(explode(',', (string) $ham))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->values()
            ->all();

        return $liste ?: null;
    }
}
