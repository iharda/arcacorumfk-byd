<?php

namespace App\Servisler;

use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Kart;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * Basın kartı üretimi -- Plan v1.0 md.6.
 *
 * Ekrandaki kart ile PDF AYNI Blade şablonundan çıkar; iki ayrı tasarım
 * bakılmaz. Her üretim yeni SÜRÜM açar, öncekini arşivler — kart yeniden
 * üretildiğinde eskisinin izi kaybolmasın.
 */
class KartUretici
{
    public function __construct(
        private QrImzalayici $qr,
        private EvrakYukleyici $evrak,
        private DenetimYazici $denetim,
    ) {}

    /**
     * @param  ?int  $uretenId  Tetikleyen kullanıcı. 💀 Burada `auth()->id()`
     *                          okunuyordu ama bu metot YALNIZCA kuyruk işinden
     *                          çağrılıyor ve işçide oturum YOKTUR:
     *                          `kartlar.ureten_id` her zaman NULL kalıyordu
     *                          (Düzeltme listesi md.15). "Kartı yeniden üret"
     *                          düğmesine basan yetkili iz bırakmıyordu.
     */
    public function uret(Akreditasyon $akreditasyon, ?int $uretenId = null): Kart
    {
        $akreditasyon->loadMissing(['kullanici', 'kurum', 'basvuru.evraklar.turu']);

        $html = $this->html($akreditasyon);
        $disk = config('byd.kart_disk');
        $surum = ((int) $akreditasyon->kartlar()->max('surum')) + 1;

        $temel = sprintf('%s/%s-s%d', $akreditasyon->ulid, $akreditasyon->kart_no, $surum);
        $pdfYolu = $temel.'.pdf';
        $gorselYolu = $temel.'.png';

        [$en, $boy] = [config('byd.kart.genislik_mm'), config('byd.kart.yukseklik_mm')];

        Storage::disk($disk)->put($pdfYolu, $this->tarayici($html)
            ->paperSize($en, $boy, 'mm')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->pdf());

        // Panelde ve kapı ekranında gösterilen önizleme. 3× ölçek: küçük
        // ekranda büyütülünce bulanıklaşmasın.
        Storage::disk($disk)->put($gorselYolu, $this->tarayici($html)
            ->windowSize((int) round($en * 96 / 25.4), (int) round($boy * 96 / 25.4))
            ->deviceScaleFactor(3)
            ->screenshot());

        return DB::transaction(function () use ($akreditasyon, $disk, $surum, $pdfYolu, $gorselYolu, $uretenId) {
            $akreditasyon->kartlar()->update(['arsiv' => true]);
            // Dosya silme COMMIT SONRASINA bırakılır: işlem geri sararsa
            // kayıtlar geri gelir ama silinmiş dosya geri gelmez.
            $this->eskiSurumleriTemizle($akreditasyon, $surum);

            $kart = $akreditasyon->kartlar()->create([
                'surum' => $surum,
                'disk' => $disk,
                'pdf_yolu' => $pdfYolu,
                'gorsel_yolu' => $gorselYolu,
                'boyut' => Storage::disk($disk)->size($pdfYolu),
                'qr_anahtar_surumu' => (int) config('byd.qr.anahtar_surumu'),
                'arsiv' => false,
                'uretildi_at' => now(),
                'ureten_id' => $uretenId,
            ]);

            $this->denetim->yaz('kart.uretildi', $kart, yeni: [
                'kart_no' => $akreditasyon->kart_no, 'surum' => $surum,
            ]);

            return $kart;
        });
    }

    /**
     * Eski kart sürümlerinin DOSYALARINI siler; KAYITLARI durur.
     *
     * Gerekçe: bir kart PDF'i ~650 KB (gömülü yazı tipleri). Her yeniden
     * üretim yeni sürüm açtığı için dosyalar sınırsız birikirdi. Güncel sürüm
     * ve bir öncekini tutuyoruz — geri dönmek gerekirse elimizde olsun.
     * Kimin ne zaman hangi sürümü ürettiği bilgisi kayıtlarda KALIR (md.10).
     */
    private function eskiSurumleriTemizle(Akreditasyon $akreditasyon, int $yeniSurum): void
    {
        $akreditasyon->kartlar()
            ->where('surum', '<', $yeniSurum - 1)
            ->whereNotNull('pdf_yolu')
            ->get()
            ->each(function (Kart $kart) {
                $silinecek = array_filter([$kart->pdf_yolu, $kart->gorsel_yolu]);
                $disk = $kart->disk;

                $kart->forceFill(['pdf_yolu' => null, 'gorsel_yolu' => null])->saveQuietly();

                DB::afterCommit(function () use ($disk, $silinecek) {
                    foreach ($silinecek as $yol) {
                        rescue(fn () => Storage::disk($disk)->delete($yol), report: false);
                    }
                });
            });
    }

    /** Şablonu doldurur. Önizleme de aynı HTML'i kullanır. */
    public function html(Akreditasyon $akreditasyon): string
    {
        $bolgeAdlari = (array) Ayar::al('bolgeler', []);

        return Blade::render(
            file_get_contents(resource_path('views/kart/basin-karti.blade.php')),
            [
                'akreditasyon' => $akreditasyon,
                'en' => config('byd.kart.genislik_mm'),
                'boy' => config('byd.kart.yukseklik_mm'),
                'isim' => $akreditasyon->kullanici?->name ?? '—',
                'kurum' => $akreditasyon->kurum?->resmi_unvan,
                'turEtiketi' => $akreditasyon->basvuru?->tur->etiket() ?? 'Basın kartı',
                'sezon' => $akreditasyon->sezon,
                'bolgeler' => collect($akreditasyon->bolge_yetkileri ?? [])
                    ->map(fn ($b) => $bolgeAdlari[$b] ?? $b)->all(),
                'armaVeri' => $this->armaVeri(),
                'font' => $this->fontVeri(),
                'fotoVeri' => $this->fotoVeri($akreditasyon),
                'qrSvg' => $this->qrSvg($akreditasyon),
            ],
        );
    }

    private function tarayici(string $html): Browsershot
    {
        $yol = config('byd.chrome.yol');

        if (blank($yol) || ! is_file($yol)) {
            throw new RuntimeException('Chrome bulunamadı. .env içindeki BYD_CHROME yolunu kontrol edin.');
        }

        return Browsershot::html($html)
            ->setChromePath($yol)
            ->setNodeBinary(config('byd.chrome.node'))
            ->setNpmBinary(config('byd.chrome.npm'))
            ->setNodeModulePath(config('byd.chrome.modüller'))
            // Konteyner/paylaşımlı sunucuda /dev/shm küçük olabiliyor.
            ->addChromiumArguments(['no-sandbox', 'disable-dev-shm-usage'])
            ->waitUntilNetworkIdle();
    }

    private function qrSvg(Akreditasyon $akreditasyon): string
    {
        $sonuc = (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $this->qr->yukUret($akreditasyon),
            // Yüksek düzeltme: kart buruşsa/ekran parlasa da okunsun.
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 0,
            foregroundColor: new Color(22, 24, 29),
        ))->build();

        return $sonuc->getString();
    }

    /**
     * Yazı tipi karta GÖMÜLÜR (resources/fonts, Inter alt kümesi).
     * Sunucuda hangi fontlar kurulu olursa olsun kart birebir aynı çıksın —
     * devir sonrası "kart bozuldu" durumunu engeller. Dış istek YOK.
     *
     * @return array<string, string>
     */
    private function fontVeri(): array
    {
        static $onbellek = null;

        return $onbellek ??= collect(['400-latin', '400-ext', '600-latin', '600-ext'])
            ->mapWithKeys(function (string $ad) {
                $yol = resource_path("fonts/kart-{$ad}.woff2");

                return [$ad => is_file($yol)
                    // Font yoksa kart yine üretilsin; şablon sistem fontuna düşer.
                    ? 'data:font/woff2;base64,'.base64_encode(file_get_contents($yol))
                    : 'about:blank'];
            })
            ->all();
    }

    private function armaVeri(): string
    {
        $yol = public_path('marka/kulup-logo.webp');

        return 'data:image/webp;base64,'.base64_encode(file_get_contents($yol));
    }

    /** Biyometrik fotoğraf — şifreliyse sunucuda çözülüp gömülür. */
    private function fotoVeri(Akreditasyon $akreditasyon): ?string
    {
        $foto = $akreditasyon->basvuru?->evraklar
            ->first(fn ($e) => $e->turu?->kod === 'biyometrik_fotograf');

        if (! $foto) {
            return null;
        }

        return rescue(
            fn () => 'data:'.$foto->mime.';base64,'.base64_encode($this->evrak->icerik($foto)),
            null,
            report: false,
        );
    }
}
