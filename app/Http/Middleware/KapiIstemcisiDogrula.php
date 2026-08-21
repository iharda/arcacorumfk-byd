<?php

namespace App\Http\Middleware;

use App\Models\KapiIstemcisi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turnike/gişe istemcisi kimlik doğrulaması -- Plan v1.0 md.7 ve md.11:
 * "her istemci: ayrı API anahtarı + IP beyaz listesi + TLS".
 *
 * 🔒 Anahtar veritabanında YALNIZCA hash olarak durur. Karşılaştırma sabit
 * sürelidir; anahtar önekinden kayıt bulunup tam hash doğrulanır.
 */
class KapiIstemcisiDogrula
{
    public function handle(Request $istek, Closure $sonraki): Response
    {
        $anahtar = trim((string) $istek->header('X-Kapi-Anahtar'));

        if ($anahtar === '') {
            return $this->hata('Anahtar eksik.', 401);
        }

        $istemci = KapiIstemcisi::query()
            ->where('anahtar_onek', substr($anahtar, 0, 12))
            ->where('aktif', true)
            ->first();

        if ($istemci === null || ! hash_equals($istemci->anahtar_hash, hash('sha256', $anahtar))) {
            return $this->hata('Anahtar geçersiz.', 401);
        }

        // IP kısıtı tanımlıysa uygula. $istek->ip() nginx'in yazdığı gerçek
        // ziyaretçi IP'si (trustProxies + cloudflare-real-ip).
        if (filled($istemci->ip_listesi) && ! IpUtils::checkIp($istek->ip(), $istemci->ip_listesi)) {
            return $this->hata('Bu adresten erişim yok.', 403);
        }

        $istek->attributes->set('kapi_istemcisi', $istemci);

        return $sonraki($istek);
    }

    private function hata(string $mesaj, int $kod): Response
    {
        // Ayrıntı verme: hangi kontrolde takıldığı saldırgana ipucu olmasın.
        return response()->json(['sonuc' => 'reddedildi', 'mesaj' => $mesaj], $kod);
    }
}
