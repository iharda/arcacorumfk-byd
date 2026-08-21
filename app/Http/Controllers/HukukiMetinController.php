<?php

namespace App\Http\Controllers;

use App\Models\Ayar;
use Illuminate\View\View;

/**
 * KVKK metinleri -- Plan v1.0 md.11.
 *
 * Metinlerin İÇERİĞİ kulüpten gelir; sistem yalnızca yerini tutar. Metin
 * girilmediyse sayfa BOŞ GÖSTERİLMEZ, eksik olduğu açıkça yazılır — yanlışlıkla
 * "aydınlatma yapıldı" izlenimi doğmasın.
 */
class HukukiMetinController extends Controller
{
    private const METINLER = [
        'aydinlatma' => ['anahtar' => 'kvkk_aydinlatma_metni', 'baslik' => 'Aydınlatma metni'],
        'acik-riza' => ['anahtar' => 'kvkk_riza_metni',        'baslik' => 'Açık rıza metni'],
        'gizlilik' => ['anahtar' => 'gizlilik_metni',         'baslik' => 'Gizlilik politikası'],
    ];

    public function goster(string $tur): View
    {
        abort_unless(isset(self::METINLER[$tur]), 404);

        $tanim = self::METINLER[$tur];

        return view('hukuki.metin', [
            'baslik' => $tanim['baslik'],
            'icerik' => Ayar::al($tanim['anahtar']),
            'guncelleme' => Ayar::al($tanim['anahtar'].'_guncelleme'),
        ]);
    }

    /** @return array<string, string> tür => başlık */
    public static function turler(): array
    {
        return array_map(fn ($t) => $t['baslik'], self::METINLER);
    }
}
