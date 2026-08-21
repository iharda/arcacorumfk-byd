<?php

namespace App\Http\Controllers;

use App\Models\KapiIstemcisi;
use App\Servisler\KapiDogrulama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Doğrulama API'si -- Plan v1.0 md.6.
 *
 * Tek indeksli sorgu (akreditasyonlar.ulid benzersiz), hafif yanıt.
 * Yanıtta fotoğraf ZORUNLU: görevli yüz kontrolü yapacak.
 */
class KapiController extends Controller
{
    public function __construct(private KapiDogrulama $dogrulama) {}

    /** Kapı uygulaması (PWA). Anahtar cihazda saklanır, sunucuda oturum yok. */
    public function uygulama(): View
    {
        return view('kapi.uygulama');
    }

    /** PWA tanım dosyası. Ayrı rota: kapı uygulaması ana ekrana eklenebilsin. */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name'             => 'ARCA Çorum FK — Kapı Doğrulama',
            'short_name'       => 'BYD Kapı',
            'start_url'        => '/kapi',
            'scope'            => '/kapi',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#16181D',
            'theme_color'      => '#16181D',
            'icons'            => [
                ['src' => asset('marka/favicon-64.png'), 'sizes' => '64x64', 'type' => 'image/png'],
                ['src' => asset('marka/apple-touch-icon.png'), 'sizes' => '180x180', 'type' => 'image/png'],
                ['src' => asset('marka/kulup-logo-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ]);
    }

    /**
     * Servis çalışanı. Yalnızca UYGULAMA KABUĞUNU önbelleğe alır.
     * ⚠️ Doğrulama yanıtları ASLA önbelleğe alınmaz: iptal edilmiş bir kartın
     * eski "izinli" yanıtı tekrar gösterilirse sistemin tüm anlamı kaybolur.
     */
    public function serviceWorker(): Response
    {
        $js = <<<'JS'
        const KABUK = 'byd-kapi-v1';

        self.addEventListener('install', (e) => {
            e.waitUntil(caches.open(KABUK).then((c) => c.addAll(['/kapi'])).then(() => self.skipWaiting()));
        });

        self.addEventListener('activate', (e) => {
            e.waitUntil(caches.keys()
                .then((a) => Promise.all(a.filter((k) => k !== KABUK).map((k) => caches.delete(k))))
                .then(() => self.clients.claim()));
        });

        self.addEventListener('fetch', (e) => {
            const url = new URL(e.request.url);

            // Doğrulama ve tanım uçları HER ZAMAN ağdan; önbellek YOK.
            if (url.pathname.startsWith('/api/')) return;

            if (e.request.mode === 'navigate') {
                e.respondWith(fetch(e.request).catch(() => caches.match('/kapi')));
                return;
            }

            e.respondWith(caches.match(e.request).then((c) => c || fetch(e.request).then((y) => {
                if (y.ok && url.origin === self.location.origin) {
                    const kopya = y.clone();
                    caches.open(KABUK).then((k) => k.put(e.request, kopya));
                }
                return y;
            })));
        });
        JS;

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function tanim(Request $istek): JsonResponse
    {
        $istemci = $istek->attributes->get('kapi_istemcisi');

        return response()->json([
            'kapi'     => $istemci->ad,
            'kapiKodu' => $istemci->kapi_kodu,
            'bolgeler' => $istemci->bolgeler ?? [],
        ]);
    }

    public function dogrula(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'yuk'   => ['required', 'string', 'max:200'],
            'yon'   => ['nullable', 'in:giris,cikis'],
            'bolge' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var KapiIstemcisi $istemci */
        $istemci = $istek->attributes->get('kapi_istemcisi');

        $sonuc = $this->dogrulama->dogrula(
            $istemci,
            $veri['yuk'],
            $veri['yon'] ?? 'giris',
            $veri['bolge'] ?? null,
            $istek->ip(),
        );

        $akreditasyon = $sonuc['akreditasyon'];

        return response()->json([
            'sonuc'    => $sonuc['sonuc']->value,
            'izinli'   => $sonuc['sonuc']->basarili(),
            'etiket'   => $sonuc['sonuc']->etiket(),
            'mesaj'    => $sonuc['mesaj'],
            'kisi'     => $akreditasyon ? [
                'isim'     => $akreditasyon->kullanici?->name,
                'kurum'    => $akreditasyon->kurum?->resmi_unvan,
                'kartNo'   => $akreditasyon->kart_no,
                'bolgeler' => $akreditasyon->bolge_yetkileri ?? [],
                'foto'     => $this->dogrulama->fotoVeri($akreditasyon),
            ] : null,
            'zaman'    => now()->timezone('Europe/Istanbul')->format('H:i:s'),
        ]);
    }
}
