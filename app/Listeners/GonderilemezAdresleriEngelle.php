<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Teslim edilmesi İMKÂNSIZ adreslere posta göndermeyi engeller.
 *
 * `.test`, `.invalid`, `.example`, `.localhost` ayrılmış uzantılardır (RFC 2606
 * / RFC 6761) ve hiçbir zaman gerçek bir posta kutusuna karşılık gelmezler.
 * Bunlara gönderim yapmak yalnızca GERİ DÖNÜŞ üretir; tekrarlanan geri dönüşler
 * gönderen itibarını düşürür ve bir noktada gerçek postalarımız da spam'e düşer.
 *
 * Uçtan uca testler bu adresleri kullanıyor — kural olmasaydı her test koşusu
 * kendi kuyruğumuza zarar verirdi.
 */
class GonderilemezAdresleriEngelle
{
    /** RFC 2606 / RFC 6761 ile ayrılmış, çözümlenemeyen uzantılar. */
    private const AYRILMIS = ['.test', '.invalid', '.example', '.localhost'];

    public function handle(MessageSending $olay): bool
    {
        $alicilar = array_map(
            fn ($a) => strtolower($a->getAddress()),
            $olay->message->getTo() ?: [],
        );

        if ($alicilar === []) {
            return true;
        }

        $gonderilemez = array_filter(
            $alicilar,
            fn (string $adres) => (bool) array_filter(
                self::AYRILMIS,
                fn (string $uzanti) => str_ends_with($adres, $uzanti),
            ),
        );

        // Alıcıların TAMAMI ayrılmış uzantıdaysa gönderimi iptal et.
        if (count($gonderilemez) !== count($alicilar)) {
            return true;
        }

        Log::info('Gönderilemez adrese posta engellendi', [
            'alicilar' => $alicilar,
            'konu' => $olay->message->getSubject(),
        ]);

        return false;   // false döndürmek gönderimi iptal eder
    }
}
