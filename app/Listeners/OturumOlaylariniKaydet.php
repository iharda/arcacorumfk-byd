<?php

namespace App\Listeners;

use App\Models\DenetimKaydi;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Request;

/**
 * Oturum olayları -- Plan v1.0 md.10 "erişim logu: oturum olayları".
 *
 * 🔑 BAŞARISIZ girişler de yazılır: kaba kuvvet ve ele geçirilmiş hesap
 * tespitinin tek kaynağı bunlar.
 * 🔒 Parola HİÇBİR ZAMAN loga yazılmaz — yalnızca denenen e-posta.
 *    (ACFK kariyer sitesinde parola adres çubuğuna düşmüştü; aynı hataya
 *    başka bir kapıdan girmeyelim.)
 */
class OturumOlaylariniKaydet
{
    public function girdi(Login $olay): void
    {
        $this->yaz('oturum.giris', $olay->user);

        if ($olay->user instanceof User) {
            $olay->user->forceFill(['son_giris_at' => now()])->saveQuietly();
        }
    }

    public function cikti(Logout $olay): void
    {
        $this->yaz('oturum.cikis', $olay->user);
    }

    public function basarisiz(Failed $olay): void
    {
        $this->yaz('oturum.basarisiz', $olay->user, [
            // Kullanıcı var mı yok mu bilgisini de saklıyoruz: var olan bir
            // hesaba yapılan denemeler ile rastgele tarama farklı şeylerdir.
            'denenen_eposta' => $olay->credentials['email'] ?? null,
            'hesap_var' => $olay->user !== null,
        ]);
    }

    public function kilitlendi(Lockout $olay): void
    {
        $this->yaz('oturum.kilitlendi', null, [
            'denenen_eposta' => $olay->request->input('email'),
        ]);
    }

    public function sifreSifirlandi(PasswordReset $olay): void
    {
        $this->yaz('oturum.sifre_sifirlandi', $olay->user);
    }

    private function yaz(string $olay, mixed $kullanici, array $yeni = []): void
    {
        DenetimKaydi::create([
            'aktor_id' => $kullanici instanceof User ? $kullanici->getKey() : null,
            'aktor_tip' => $kullanici instanceof User ? 'kullanici' : 'anonim',
            'aktor_ad' => $kullanici instanceof User ? $kullanici->name : null,
            'olay' => $olay,
            'kayit_tipi' => $kullanici instanceof User ? User::class : null,
            'kayit_id' => $kullanici instanceof User ? $kullanici->getKey() : null,
            'yeni' => $yeni ?: null,
            'ip' => Request::ip(),
            'tarayici' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
