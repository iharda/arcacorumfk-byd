<?php

namespace App\Servisler;

use App\Models\KapiIstemcisi;
use Illuminate\Support\Facades\DB;
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

        $istemci = DB::transaction(function () use ($veri, $anahtar) {
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

            return $istemci;
        });

        return ['istemci' => $istemci, 'anahtar' => $anahtar];
    }

    public function anahtariYenile(KapiIstemcisi $istemci): string
    {
        $anahtar = $this->anahtarUret();

        DB::transaction(function () use ($istemci, $anahtar) {
            $istemci->update([
                'anahtar_onek' => substr($anahtar, 0, 12),
                'anahtar_hash' => hash('sha256', $anahtar),
            ]);

            $this->denetim->yaz('kapi_istemcisi.anahtar_yenilendi', $istemci,
                not: 'Eski anahtar anında geçersiz oldu.');
        });

        return $anahtar;
    }

    /** Çakışma olmayan, okunabilir bir anahtar. */
    private function anahtarUret(): string
    {
        do {
            /*
             * 💀 Eski yorum "karışan karakter yok (0/O, 1/l)" diyordu ama
             * `Str::random()` alfabesi `0-9a-zA-Z`; `Str::lower()` sonrası
             * `0`, `o`, `1`, `l` HEPSİ duruyordu (Düzeltme listesi md.18.4).
             * Yorum güvence veriyor, kod vermiyordu. Artık veriyor:
             * Crockford base32 -- `i`, `l`, `o`, `u` yok.
             */
            $anahtar = 'kapi_'.$this->karismayanKod(40);
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

    /**
     * Görevli anahtarı ELLE girebilsin diye karışan karakterler yok:
     * Crockford base32 (`0-9` + `a-z` eksi `i`, `l`, `o`, `u`).
     */
    private function karismayanKod(int $uzunluk): string
    {
        $alfabe = '0123456789abcdefghjkmnpqrstvwxyz';
        $kod = '';

        for ($i = 0; $i < $uzunluk; $i++) {
            $kod .= $alfabe[random_int(0, strlen($alfabe) - 1)];
        }

        return $kod;
    }
}
