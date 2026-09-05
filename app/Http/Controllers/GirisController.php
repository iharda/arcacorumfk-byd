<?php

namespace App\Http\Controllers;

use App\Filament\Yonetim\Auth\YonetimGirisi;
use App\Models\User;
use App\Support\GirisHedefi;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TEK GİRİŞ KAPISI -- Revizyon md.4.
 *
 * Kurum yetkilisi, basın mensubu ve içerik üreticisi aynı adresten girer;
 * sistem kişiyi rolüne göre kendi paneline yollar. "Hangi girişten gireceğim"
 * sorusu ve yanlış kapıdan girip 403 alma hâli kaynağında biter.
 *
 * 🔑 Kulüp yetkilisi buradan GİRMEZ: onun kapısı `/yonetim/login` ve orada
 * iki adımlı doğrulama zorunlu. Burada oturum açtırsaydık 2FA'yı atlatan bir
 * yan kapı açmış olurduk.
 */
class GirisController extends Controller
{
    /** Aynı e-posta + IP için ardışık başarısız deneme hakkı. */
    private const DENEME_HAKKI = 5;

    /** Kilit süresi (saniye). */
    private const KILIT = 600;

    public function form(Request $istek): View|RedirectResponse
    {
        $kullanici = Auth::user();

        if ($kullanici instanceof User) {
            $hedef = $this->hedef($istek, $kullanici);

            if ($hedef !== null) {
                return redirect()->to($hedef);
            }

            // Oturumu var ama girebileceği panel yok: kapıda bırakma, çıkar.
            $this->oturumuKapat($istek);

            return view('giris.giris')->with('uyari', $this->etkinDegilMesaji());
        }

        return view('giris.giris');
    }

    public function giris(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ],
            [],
            ['email' => 'e-posta', 'password' => 'şifre'],
        );

        $anahtar = $this->sinirAnahtari($istek, $veri['email']);

        if (RateLimiter::tooManyAttempts($anahtar, self::DENEME_HAKKI)) {
            /*
             * 🔑 Kilit olayını ELLE tetikliyoruz: `throttle` ara katmanı bunu
             * yapmaz ve kaba kuvvetin denetim kaydındaki tek izi budur.
             */
            event(new Lockout($istek));

            throw ValidationException::withMessages([
                'email' => 'Çok fazla başarısız deneme. '
                    .ceil(RateLimiter::availableIn($anahtar) / 60).' dakika sonra tekrar deneyin.',
            ]);
        }

        if (! Auth::attempt($veri, $istek->boolean('hatirla'))) {
            RateLimiter::hit($anahtar, self::KILIT);

            // Hesap var mı yok mu SIZDIRILMAZ: iki hâlde de aynı cümle.
            throw ValidationException::withMessages(['email' => 'E-posta veya şifre hatalı.']);
        }

        RateLimiter::clear($anahtar);

        /** @var User $kullanici */
        $kullanici = Auth::user();

        if ($kullanici->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            $this->oturumuKapat($istek);

            Notification::make()
                ->title('Kulüp yetkilisi girişi bu sayfadan yapılır')
                ->body('İki adımlı doğrulama burada zorunlu.')
                ->info()
                ->send();

            /*
             * 🔑 E-POSTA TAŞINIR, ŞİFRE TAŞINMAZ. Kişi iki alanı da doğru
             * doldurmuştu; ikisini birden yeniden yazdırmak boş sürtünme.
             * Sızıntı değil: buraya ancak şifre DOĞRUYSA düşülüyor, yani
             * "bu adres yönetici mi" bilgisi zaten karşı tarafta.
             *
             * 🪤 Flash, oturum `oturumuKapat()` ile geçersizleştirildikten
             * SONRA yazılıyor; yeni oturuma düşer, yoksa silinirdi.
             */
            return redirect()->route('filament.yonetim.auth.login')
                ->with(YonetimGirisi::EPOSTA_ANAHTARI, $veri['email']);
        }

        $istek->session()->regenerate();

        $hedef = $this->hedef($istek, $kullanici);

        if ($hedef === null) {
            $this->oturumuKapat($istek);

            throw ValidationException::withMessages(['email' => $this->etkinDegilMesaji()]);
        }

        return redirect()->to($hedef);
    }

    /**
     * Panel seçim ekranı -- yalnızca birden çok panele girebilen kullanıcı
     * için (gazete sahibi aynı zamanda muhabir olabilir). Tek paneli olan
     * buraya düşerse doğrudan panele gider: gereksiz bir tıklama koymayız.
     */
    public function panelSec(Request $istek): View|RedirectResponse
    {
        /** @var User $kullanici */
        $kullanici = Auth::user();
        $paneller = $kullanici->erisebildigiPaneller();

        if (count($paneller) < 2) {
            return redirect()->to($paneller === [] ? route('anasayfa') : array_key_first($paneller));
        }

        return view('giris.panel-sec', ['paneller' => $paneller, 'kullanici' => $kullanici]);
    }

    public function cikis(Request $istek): RedirectResponse
    {
        $this->oturumuKapat($istek);

        return redirect()->route('giris');
    }

    /**
     * Girişten sonra gidilecek adres; girebileceği panel yoksa null.
     * Birden çok panel varsa seçim ekranı.
     */
    private function hedef(Request $istek, User $kullanici): ?string
    {
        $paneller = $kullanici->erisebildigiPaneller();

        return match (true) {
            $paneller === [] => null,
            count($paneller) > 1 => route('panel.sec'),
            default => GirisHedefi::belirle($istek, (string) array_key_first($paneller)),
        };
    }

    private function oturumuKapat(Request $istek): void
    {
        Auth::logout();
        $istek->session()->invalidate();
        $istek->session()->regenerateToken();
    }

    private function etkinDegilMesaji(): string
    {
        return 'Hesabınız şu anda etkin değil. Başvurunuz sonuçlanmadıysa sonucu e-posta ile bildireceğiz.';
    }

    /** Sınır anahtarı e-posta + IP: bir kişinin denemesi başkasını kilitlemesin. */
    private function sinirAnahtari(Request $istek, string $eposta): string
    {
        return 'giris|'.Str::transliterate(Str::lower($eposta)).'|'.$istek->ip();
    }
}
