<?php

namespace App\Http\Controllers;

use App\Models\Evrak;
use App\Servisler\DenetimYazici;
use App\Servisler\EvrakYukleyici;
use Illuminate\Http\Response;

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
    public function __construct(
        private EvrakYukleyici $yukleyici,
        private DenetimYazici $denetim,
    ) {}

    public function goster(Evrak $evrak): Response
    {
        $this->authorize('view', $evrak);

        $evrak->loadMissing('turu');

        // Hassas evraka erişim ayrıca loglanır; sıradan evrakta gürültü olmasın.
        if ($evrak->turu?->hassas) {
            $this->denetim->yaz('evrak.goruntulendi', $evrak, yeni: [
                'evrak_turu' => $evrak->turu->kod,
            ]);
        }

        return response($this->yukleyici->icerik($evrak), 200, [
            'Content-Type'           => $evrak->mime,
            'Content-Disposition'    => 'inline; filename="' . addslashes($evrak->orijinal_ad) . '"',
            'Cache-Control'          => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
