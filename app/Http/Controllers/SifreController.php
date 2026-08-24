<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Servisler\DenetimYazici;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as SifreKurali;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Şifre sıfırlama -- Revizyon md.4.5. TEK rota, üç panel için.
 *
 * Panellerin kendi `->passwordReset()` sayfaları kaldırıldı: üç ayrı kapı,
 * üç ayrı e-posta biçimi ve "hangisinden isteyeceğim" sorusu demekti.
 *
 * 🔒 "Bu adres kayıtlı mı" bilgisi SIZDIRILMAZ: adres bulunsa da bulunmasa da
 * aynı cümle döner.
 */
class SifreController extends Controller
{
    public function __construct(private DenetimYazici $denetim) {}

    public function istekFormu(): View
    {
        return view('giris.sifremi-unuttum');
    }

    public function istekGonder(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(
            ['email' => ['required', 'email']],
            [],
            ['email' => 'e-posta'],
        );

        // Dönen durum bilerek YOK SAYILIR (hesap var mı sızmasın); Laravel'in
        // kendi kısıtı (60 sn) aynı adrese art arda posta gönderilmesini keser.
        Password::sendResetLink($veri);

        return back()->with('bilgi', 'Bu adres kayıtlıysa şifre belirleme bağlantısı gönderildi. Gelen kutunuzu kontrol edin.');
    }

    public function sifirlamaFormu(Request $istek, string $token): View
    {
        return view('giris.sifre-sifirla', [
            'token' => $token,
            'eposta' => (string) $istek->query('email', ''),
        ]);
    }

    public function sifirla(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(
            [
                'token' => ['required', 'string'],
                'email' => ['required', 'email'],
                'sifre' => ['required', 'confirmed', SifreKurali::min(10)->letters()->numbers()->uncompromised()],
            ],
            [],
            ['email' => 'e-posta', 'sifre' => 'şifre'],
        );

        $kullanici = null;

        $durum = Password::reset(
            [
                'email' => $veri['email'],
                'password' => $veri['sifre'],
                'password_confirmation' => $istek->input('sifre_confirmation'),
                'token' => $veri['token'],
            ],
            function (User $u, string $sifre) use (&$kullanici) {
                $u->forceFill([
                    'password' => $sifre,
                    'remember_token' => Str::random(60),
                    // Geçerli bağlantı e-posta sahipliğini KANITLAR: hiç
                    // etkinleştirilmemiş hesap da burada doğrulanmış sayılır.
                    'email_verified_at' => $u->email_verified_at ?? now(),
                ])->save();

                $this->denetim->yaz('hesap.sifre_sifirlandi', $u, aktorTip: 'sistem');

                event(new PasswordReset($u));

                $kullanici = $u;
            },
        );

        if ($durum !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'Bağlantı geçersiz ya da süresi dolmuş. Yeniden şifre belirleme isteyin.',
            ]);
        }

        /*
         * 🔒 Yetkiliyi BURADA oturuma almayız: yönetim panelinde iki adımlı
         * doğrulama zorunlu, doğrudan giriş onu atlatırdı. Şifre sıfırlama
         * 2FA'nın yan kapısı olamaz.
         */
        if ($kullanici?->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            Notification::make()
                ->title('Şifreniz güncellendi')
                ->body('Yeni şifrenizle giriş yapın.')
                ->success()
                ->send();

            return redirect()->route('filament.yonetim.auth.login');
        }

        Auth::login($kullanici);
        $istek->session()->regenerate();

        return redirect()->route('giris');
    }
}
