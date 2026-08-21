<?php

namespace App\Servisler;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapor dışa aktarımı -- Plan v1.0 md.8.
 *
 * Akış hâlinde yazar: maç günü on binlerce satır olabilir, hepsini belleğe
 * almak PHP'yi düşürür. Excel Türkçe karakterleri doğru açsın diye BOM eklenir.
 */
class CsvDisaAktar
{
    /**
     * @param  array<int, string>  $basliklar
     * @param  callable(object): array<int, mixed>  $satir
     */
    public function akit(Builder $sorgu, string $dosyaAdi, array $basliklar, callable $satir): StreamedResponse
    {
        return Response::streamDownload(function () use ($sorgu, $basliklar, $satir) {
            $cikti = fopen('php://output', 'w');
            fwrite($cikti, "\xEF\xBB\xBF");
            fputcsv($cikti, $basliklar, ';');

            $sorgu->chunkById(500, function ($kayitlar) use ($cikti, $satir) {
                foreach ($kayitlar as $kayit) {
                    fputcsv($cikti, $satir($kayit), ';');
                }
            });

            fclose($cikti);
        }, $dosyaAdi . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
