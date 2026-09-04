<?php

namespace App\Servisler;

use App\Models\DenetimKaydi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Denetim kaydı yazıcısı -- Plan v1.0 md.10.
 * Kim, ne zaman, hangi kayıtta ne değiştirdi (eski → yeni).
 *
 * Aktörün ADI da yazılır: kullanıcı sonradan silinse bile kaydın kim tarafından
 * yapıldığı kaybolmaz (foreign key null'a düşer, ad kalır).
 */
class DenetimYazici
{
    public function yaz(
        string $olay,
        ?Model $kayit = null,
        ?array $eski = null,
        ?array $yeni = null,
        ?string $not = null,
        string $aktorTip = 'kullanici',
    ): DenetimKaydi {
        $aktor = Auth::user();

        return DenetimKaydi::create([
            'aktor_id' => $aktor?->getKey(),
            'aktor_tip' => $aktor ? $aktorTip : 'sistem',
            'aktor_ad' => $aktor?->name,
            'olay' => $olay,
            'kayit_tipi' => $kayit ? $kayit::class : null,
            'kayit_id' => $kayit?->getKey(),
            'kayit_etiketi' => $kayit ? $this->etiket($kayit) : null,
            'eski' => $eski,
            'yeni' => $yeni,
            'not' => $not,
            'ip' => Request::ip(),
            'tarayici' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * Modelin insan tarafından okunabilir kısa adı.
     *
     * 🪤 SÜTUNLARA bakar, körlemesine `getAttribute()`e değil. Aradığımız
     * adlardan biriyle aynı ada sahip bir METOT tanımlanırsa (`Evrak::baslik()`
     * gibi) Laravel onu ilişki sanıp
     * `LogicException: must return a relationship instance` fırlatır.
     * Denetim yazımı evrak görüntüleme ve karar verme gibi kritik yollarda;
     * oradaki istisna isteği 500'e düşürür ve sebebi bu fonksiyonda görünmez.
     * Modelde ne tanımlanırsa tanımlansın burası kırılmamalı.
     */
    private function etiket(Model $kayit): ?string
    {
        $sutunlar = $kayit->getAttributes();

        foreach (['kart_no', 'resmi_unvan', 'name', 'baslik', 'ad', 'ulid'] as $alan) {
            if (array_key_exists($alan, $sutunlar) && filled($kayit->getAttribute($alan))) {
                return (string) $kayit->getAttribute($alan);
            }
        }

        return null;
    }
}
