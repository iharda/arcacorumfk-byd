<?php

namespace App\Http\Controllers;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Http\Requests\KurumBasvuruIstegi;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Notifications\HesapAktivasyonu;
use App\Servisler\DenetimYazici;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Kamuya açık başvuru -- Plan v1.0 md.5.1. */
class BasvuruController extends Controller
{
    public function __construct(private DenetimYazici $denetim) {}

    public function secim(): View
    {
        return view('basvuru.secim');
    }

    public function kurumFormu(): View
    {
        return view('basvuru.kurum');
    }

    public function kurumKaydet(KurumBasvuruIstegi $istek): RedirectResponse
    {
        $veri = $istek->validated();

        DB::transaction(function () use ($veri) {
            $kurum = Kurum::create([
                'resmi_unvan'        => $veri['resmi_unvan'],
                'adres'              => $veri['adres'],
                'il'                 => $veri['il'],
                'ilce'               => $veri['ilce'],
                'telefon'            => $this->telefonBicimle($veri['kurum_telefon']),
                'eposta'             => $veri['kurum_eposta'],
                'vergi_dairesi'      => $veri['vergi_dairesi'],
                'vergi_no'           => $veri['vergi_no'],
                'calisan_sayisi'     => $veri['calisan_sayisi'],
                'yayin_platformlari' => array_values($veri['yayin_platformlari']),
                'sosyal_medya'       => array_filter($veri['sosyal_medya'] ?? []),
                'akreditasyon_durumu' => 'beklemede',
            ]);

            // Hesap BAŞVURU ANINDA açılır; şifreyi kullanıcı aktivasyon
            // bağlantısıyla kendisi belirler (md.5.5) -- sistem şifre üretmez.
            $kullanici = User::create([
                'name'     => $veri['yetkili_ad'],
                'email'    => $veri['yetkili_eposta'],
                'password' => Hash::make(Str::random(64)),   // kullanılamaz, yer tutucu
                'telefon'  => $this->telefonBicimle($veri['yetkili_telefon']),
                'kurum_id' => $kurum->id,
                'aktif'    => true,
            ]);
            $kullanici->assignRole(User::ROL_KURUM);

            $basvuru = Basvuru::create([
                'tur'          => BasvuruTuru::Kurum,
                'durum'        => BasvuruDurumu::Taslak,
                'kullanici_id' => $kullanici->id,
                'kurum_id'     => $kurum->id,
                'form_verisi'  => [
                    'yetkili_ad'      => $veri['yetkili_ad'],
                    'yetkili_telefon' => $veri['yetkili_telefon'],
                    'kvkk_onay_at'    => now()->toIso8601String(),
                ],
            ]);

            $this->denetim->yaz('basvuru.olusturuldu', $basvuru,
                yeni: ['tur' => 'kurum', 'kurum' => $kurum->resmi_unvan],
                aktorTip: 'sistem');

            $kullanici->notify(new HesapAktivasyonu);
        });

        return redirect()->route('basvuru.gonderildi')
            ->with('eposta', $veri['yetkili_eposta']);
    }

    public function gonderildi(): View
    {
        abort_unless(session()->has('eposta'), 404);

        return view('basvuru.gonderildi', ['eposta' => session('eposta')]);
    }

    /**
     * Telefonu +90 XXX XXX XX XX biçimine çevirir.
     * (ValCert'te 2445 kaydı sonradan düzeltmek zorunda kalmıştık — baştan yaz.)
     */
    private function telefonBicimle(string $ham): string
    {
        $rakam = preg_replace('/\D+/', '', $ham) ?? '';
        $rakam = ltrim($rakam, '0');
        if (str_starts_with($rakam, '90')) {
            $rakam = substr($rakam, 2);
        }

        if (strlen($rakam) !== 10) {
            return $ham;   // beklenmedik biçim: olduğu gibi sakla, veri kaybetme
        }

        return sprintf('+90 %s %s %s %s',
            substr($rakam, 0, 3), substr($rakam, 3, 3), substr($rakam, 6, 2), substr($rakam, 8, 2));
    }
}
