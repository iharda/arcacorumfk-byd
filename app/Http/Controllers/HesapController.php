<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Servisler\DenetimYazici;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Hesap aktivasyonu -- Plan v1.0 md.5.5.
 * Bağlantı imzalı ve süreli (signed middleware); şifre belirlenince e-posta
 * doğrulanmış sayılır ve kullanıcı panele alınır.
 */
class HesapController extends Controller
{
    public function __construct(private DenetimYazici $denetim) {}

    public function aktivasyonFormu(Request $istek, User $kullanici): View|RedirectResponse
    {
        if ($kullanici->email_verified_at !== null) {
            // Tek giriş kapısı (Revizyon md.4): panel başına giriş sayfası yok.
            return redirect()->route('giris')
                ->with('bilgi', 'Hesabınız zaten etkin. Giriş yapabilirsiniz.');
        }

        return view('hesap.aktivasyon', ['kullanici' => $kullanici, 'imzaliAdres' => $istek->fullUrl()]);
    }

    public function aktivasyonKaydet(Request $istek, User $kullanici): RedirectResponse
    {
        if ($kullanici->email_verified_at !== null) {
            return redirect()->route('giris');
        }

        $istek->validate(
            ['sifre' => ['required', 'confirmed', Password::min(10)->letters()->numbers()->uncompromised()]],
            [],
            ['sifre' => 'şifre'],
        );

        DB::transaction(function () use ($istek, $kullanici) {
            $kullanici->forceFill([
                'password' => Hash::make($istek->string('sifre')->toString()),
                'email_verified_at' => now(),
            ])->save();

            $this->denetim->yaz('hesap.aktiflestirildi', $kullanici);
        });

        /*
         * 🔒 Yetkiliyi BURADAN oturuma almayız: yönetim panelinde iki adımlı
         * doğrulama zorunlu, doğrudan giriş onu atlatırdı.
         */
        if ($kullanici->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return redirect()->route('filament.yonetim.auth.login')
                ->with('bilgi', 'Şifreniz kaydedildi. Yeni şifrenizle giriş yapın.');
        }

        Auth::login($kullanici);
        $istek->session()->regenerate();

        // Hedefi tek yerden çözüyoruz: giriş kapısı kullanıcıyı kendi paneline
        // ya da birden çok paneli varsa seçim ekranına yollar.
        return redirect()->route('giris');
    }
}
