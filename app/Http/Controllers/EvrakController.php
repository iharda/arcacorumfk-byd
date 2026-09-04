<?php

namespace App\Http\Controllers;

use App\Models\Evrak;
use App\Models\Kart;
use App\Models\User;
use App\Servisler\DenetimYazici;
use App\Servisler\EvrakYukleyici;
use App\Servisler\IcerikAkisi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Evrak görüntüleme -- Plan v1.0 md.11.
 *
 * 🔒 Dosya HİÇBİR ZAMAN doğrudan sunulmaz: depo web root dışında, hassas
 * evrak şifreli. Buradan geçmek zorunlu, çünkü:
 *   - policy ile kapsam kontrolü yapılıyor (IDOR),
 *   - şifre çözme sunucuda oluyor,
 *   - kimlik görseline HER erişim denetim kaydına düşüyor (md.10).
 */
class EvrakController extends Controller
{
    /** İçerik diskinden `inline` servis edilebilecek TEK biçimler. */
    private const ICERIK_MIME = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'video/mp4', 'video/webm',
    ];

    public function __construct(
        private EvrakYukleyici $yukleyici,
        private DenetimYazici $denetim,
    ) {}

    /**
     * Kart görseli. Kart depoda, web root DIŞINDA; sahibi ve yetkili görebilir.
     * (QR'ı içerdiği için herkese açık bir adres OLMAMALI.)
     */
    public function kartGorseli(Kart $kart): Response
    {
        $kart->loadMissing('akreditasyon');
        $this->authorize('view', $kart->akreditasyon);

        abort_unless(filled($kart->gorsel_yolu), 404);

        return response(Storage::disk($kart->disk)->get($kart->gorsel_yolu), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Medya merkezi dosyası (duyuru görseli, bülten eki).
     * Evrak kadar hassas değil ama HERKESE AÇIK DA DEĞİL: yalnızca oturum
     * açmış akredite kullanıcılar ve içerik yetkilileri görür.
     */
    // 🪤 Dönüş tipi Symfony'nin Response'u: video yolunda `BinaryFileResponse`
    // dönüyoruz ve o `Illuminate\Http\Response`'un alt sınıfı DEĞİL.
    public function icerikDosyasi(Request $istek, string $yol): SymfonyResponse
    {
        abort_unless($this->icerigeErisebilirMi($istek->user()), 403);

        // 🔒 Yol dışarıdan geliyor: dizin dışına çıkma denemesini kes.
        abort_if(str_contains($yol, '..') || ! preg_match('#^(duyuru|bulten)/[\w.\-]+$#', $yol), 404);

        $disk = Storage::disk('icerik');
        abort_unless($disk->exists($yol), 404);

        $mime = $disk->mimeType($yol) ?: 'application/octet-stream';

        /*
         * 🔒 İKİNCİ KAPI (Düzeltme listesi md.3): yükleme beyaz listesi
         * sıkılaştırıldı ama diskte ESKİ bir SVG durabilir. Dosya `inline`
         * servis edildiği için tarayıcı SVG'yi aynı origin'de çalıştırır;
         * `nosniff` burada işe yaramaz (MIME zaten doğru, sniff yok).
         */
        abort_unless(in_array($mime, self::ICERIK_MIME, true), 404);

        /*
         * 🎬 Video parça parça istenir: `<video>` etiketi ilerlemek için
         * Range başlığı gönderir. Tüm dosyayı belleğe alıp 200 dönmek hem
         * ileri sarmayı bozar hem 60 MB'lık bir dosyayı PHP belleğine
         * yükler. `BinaryFileResponse` Range'i kendisi karşılar ve dosyayı
         * akıtır.
         */
        if (str_starts_with($mime, 'video/')) {
            $yanit = new BinaryFileResponse($disk->path($yol), 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]);

            $yanit->setContentDisposition('inline');

            return $yanit->prepare(request());
        }

        return response($disk->get($yol), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function icerigeErisebilirMi(?User $kullanici): bool
    {
        if (! $kullanici) {
            return false;
        }

        if ($kullanici->can('icerik.yonet')) {
            return true;
        }

        return IcerikAkisi::akrediteKullanicilar()
            ->whereKey($kullanici->getKey())
            ->exists();
    }

    public function goster(Evrak $evrak): Response
    {
        $this->authorize('view', $evrak);

        /*
         * 🔑 İMHA EDİLMİŞ EVRAK 410 (M2.2). Yetki kontrolünden SONRA, denetim
         * kaydından ÖNCE: "yok" cevabı da ancak bakma hakkı olana verilir,
         * ama okunamayan dosya için "görüntülendi" kaydı yazılmaz.
         *
         * 410 Gone bilerek seçildi: kaynak VARDI, kalıcı olarak gitti ve geri
         * gelmeyecek. 404 "hiç olmadı" der ve yetkiliyi aramaya iter.
         */
        abort_if($evrak->imhaEdildiMi(), 410, 'Bu evrakın saklama süresi doldu ve dosyası imha edildi.');

        $evrak->loadMissing('turu');

        // Hassas evraka erişim ayrıca loglanır; sıradan evrakta gürültü olmasın.
        if ($evrak->turu?->hassas) {
            $this->denetim->yaz('evrak.goruntulendi', $evrak, yeni: [
                'evrak_turu' => $evrak->turu->kod,
            ]);
        }

        return response($this->yukleyici->icerik($evrak), 200, [
            'Content-Type' => $evrak->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($evrak->orijinal_ad).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
