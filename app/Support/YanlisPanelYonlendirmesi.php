<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Yanlış panele düşen oturumlu kullanıcıyı çıkışsız 403 sayfasında bırakmaz.
 *
 * Üç panel aynı `web` oturumunu paylaşır; yetkili adresine giden bir bağlantı,
 * eski bir sekme ya da yer imi kurum/üye kullanıcısını doğrudan `abort(403)`'e
 * götürür. O sayfada menü de yoktur: kullanıcı ne geri dönebilir ne çıkış
 * yapabilir. Artık kendi paneline yönlendirilir.
 *
 * ⚠️ SADECE "panele hiç erişemiyor" hâlini karşılar. Erişebildiği panelin
 * içindeki policy 403'lerine (ör. akredite olmayan üye içerik sayfasını
 * açmaya çalışırsa) DOKUNMAZ — orada 403 doğru cevaptır.
 */
class YanlisPanelYonlendirmesi
{
    public static function yanit(HttpExceptionInterface $e, Request $request): ?RedirectResponse
    {
        if ($e->getStatusCode() !== 403) {
            return null;
        }

        // Turnike ucu ve XHR istekleri gövdeyi okur, yönlendirme onları bozar.
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $istenen = self::panelBul($request->segment(1));

        if (! $istenen instanceof Panel || $user->canAccessPanel($istenen)) {
            return null;
        }

        $kendi = self::panelBul(trim($user->panelYolu(), '/'));

        // Hiçbir panele giremiyorsa (pasif/ayrılmış hesap) kamu yüzüne düşür;
        // kendi paneline yollamak sonsuz döngü olurdu.
        if (! $kendi instanceof Panel || ! $user->canAccessPanel($kendi)) {
            return redirect()->route('anasayfa');
        }

        Notification::make()
            ->title('Bu bölüme erişiminiz yok')
            ->body('Kendi panelinize yönlendirildiniz.')
            ->warning()
            ->send();

        return redirect('/'.$kendi->getPath());
    }

    /** URL'in ilk parçasına karşılık gelen panel. */
    private static function panelBul(?string $yol): ?Panel
    {
        if ($yol === null || $yol === '') {
            return null;
        }

        foreach (Filament::getPanels() as $panel) {
            if ($panel->getPath() === $yol) {
                return $panel;
            }
        }

        return null;
    }
}
