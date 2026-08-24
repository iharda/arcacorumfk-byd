<?php

namespace App\Http\Controllers;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Http\Requests\BireyselBasvuruIstegi;
use App\Http\Requests\KurumBasvuruIstegi;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Notifications\HesapAktivasyonu;
use App\Notifications\YenidenBasvuruAlindi;
use App\Servisler\BasvuruUygunlugu;
use App\Servisler\DavetAkisi;
use App\Servisler\DenetimYazici;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/** Kamuya açık başvuru -- Plan v1.0 md.5.1. */
class BasvuruController extends Controller
{
    /** Son işlemde şifre belirleme bağlantısı mı gitti, bilgi maili mi? */
    private bool $aktivasyonGonderildi = true;

    public function __construct(
        private DenetimYazici $denetim,
        private DavetAkisi $davetAkisi,
        private BasvuruUygunlugu $uygunluk,
    ) {}

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

        try {
            $aktivasyon = DB::transaction(function () use ($veri) {
                $kurumVerisi = [
                    'resmi_unvan' => $veri['resmi_unvan'],
                    'adres' => $veri['adres'],
                    'il' => $veri['il'],
                    'ilce' => $veri['ilce'],
                    'telefon' => $this->telefonBicimle($veri['kurum_telefon']),
                    'eposta' => $veri['kurum_eposta'],
                    'vergi_dairesi' => $veri['vergi_dairesi'],
                    'vergi_no' => $veri['vergi_no'],
                    'calisan_sayisi' => $veri['calisan_sayisi'],
                    'yayin_platformlari' => array_values($veri['yayin_platformlari']),
                    'sosyal_medya' => array_filter($veri['sosyal_medya'] ?? []),
                    'akreditasyon_durumu' => 'beklemede',
                ];

                // Hesap BAŞVURU ANINDA açılır; şifreyi kullanıcı aktivasyon
                // bağlantısıyla kendisi belirler (md.5.5) -- sistem şifre üretmez.
                // Yeniden başvuruda hesap da kurum kaydı da TEKRAR KULLANILIR.
                $kullanici = $this->hesabiHazirla($veri['yetkili_eposta'], [
                    'name' => $veri['yetkili_ad'],
                    'telefon' => $this->telefonBicimle($veri['yetkili_telefon']),
                ], User::ROL_KURUM);

                $kurum = $this->kurumuHazirla($kullanici, $kurumVerisi);

                $kullanici->forceFill(['kurum_id' => $kurum->id])->save();

                $basvuru = Basvuru::create([
                    'tur' => BasvuruTuru::Kurum,
                    'durum' => BasvuruDurumu::Taslak,
                    'kullanici_id' => $kullanici->id,
                    'kurum_id' => $kurum->id,
                    // Hesap ONAY aninda acilacak (Revizyon md.2.1); iletisim
                    // bilgisi o gune kadar basvurunun ustunde durur.
                    'basvuran_ad' => $veri['yetkili_ad'],
                    'basvuran_eposta' => $veri['yetkili_eposta'],
                    'basvuran_telefon' => $this->telefonBicimle($veri['yetkili_telefon']),
                    'form_verisi' => [
                        'yetkili_ad' => $veri['yetkili_ad'],
                        'yetkili_telefon' => $veri['yetkili_telefon'],
                        'kvkk_onay_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->denetim->yaz('basvuru.olusturuldu', $basvuru,
                    yeni: ['tur' => 'kurum', 'kurum' => $kurum->resmi_unvan, 'yeniden' => ! $kullanici->wasRecentlyCreated],
                    aktorTip: 'sistem');

                return $this->basvuranaHaberVer($kullanici, $basvuru);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        return redirect()->route('basvuru.gonderildi')
            ->with('eposta', $veri['yetkili_eposta'])
            ->with('aktivasyon', $aktivasyon);
    }

    /* ─────────── Bireysel başvurular (md.3.2 / md.3.3) ─────────── */

    public function bireyselFormu(): View
    {
        $tur = request()->routeIs('*icerik-ureticisi*')
            ? BasvuruTuru::IcerikUreticisi
            : BasvuruTuru::BasinMensubu;

        return view('basvuru.bireysel', [
            'tur' => $tur,
            'kurumlar' => $tur === BasvuruTuru::BasinMensubu ? $this->akrediteKurumlar() : collect(),
            'davet' => null,
        ]);
    }

    public function bireyselKaydet(BireyselBasvuruIstegi $istek): RedirectResponse
    {
        $veri = $istek->validated();
        $tur = $istek->tur();

        $kurum = $tur === BasvuruTuru::BasinMensubu
            ? Kurum::where('ulid', $veri['kurum_ulid'])->firstOrFail()
            : null;

        try {
            $this->bireyselOlustur($tur, $kurum, $veri, kurumBaslatti: false);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        return redirect()->route('basvuru.gonderildi')
            ->with('eposta', $veri['eposta'])
            ->with('aktivasyon', $this->aktivasyonGonderildi);
    }

    /* ─────────── Davetle başvuru — "Yol B" (md.5.2) ─────────── */

    public function davetFormu(string $token): View
    {
        $davet = $this->davetAkisi->tokenlaBul($token)
            ?? abort(410, 'Bu davet bağlantısı geçersiz veya süresi dolmuş.');

        return view('basvuru.bireysel', [
            'tur' => BasvuruTuru::BasinMensubu,
            'kurumlar' => collect(),
            'davet' => $davet,
            'token' => $token,
        ]);
    }

    public function davetKaydet(BireyselBasvuruIstegi $istek, string $token): RedirectResponse
    {
        $davet = $this->davetAkisi->tokenlaBul($token)
            ?? abort(410, 'Bu davet bağlantısı geçersiz veya süresi dolmuş.');

        $veri = $istek->validated() + [
            'ad_soyad' => $davet->ad_soyad,
            'eposta' => $davet->eposta,
        ];

        // 🪤 Davet yolunda e-posta form kuralından GEÇMEZ (ad/e-posta davetten
        // gelir): uygunluk engeli buradan çıkar, 500 vermeden gösterilmeli.
        try {
            $basvuru = $this->bireyselOlustur(
                BasvuruTuru::BasinMensubu, $davet->kurum, $veri, kurumBaslatti: true,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        $davet->update(['kullanildi_at' => now(), 'basvuru_id' => $basvuru->id]);

        return redirect()->route('basvuru.gonderildi')
            ->with('eposta', $davet->eposta)
            ->with('aktivasyon', $this->aktivasyonGonderildi);
    }

    /** İki yolun ortak gövdesi: kurum bağı ve teyit gerekliliği dışında aynı. */
    private function bireyselOlustur(BasvuruTuru $tur, ?Kurum $kurum, array $veri, bool $kurumBaslatti): Basvuru
    {
        return DB::transaction(function () use ($tur, $kurum, $veri, $kurumBaslatti) {
            // Rol BAŞVURU ANINDA verilir: kullanıcı onaydan önce de panele girip
            // evrak yükleyebilmeli (md.5.5). Yetkinin kendisi akreditasyona bağlı.
            [$rol, $eskiRol] = $tur === BasvuruTuru::BasinMensubu
                ? [User::ROL_BASIN, User::ROL_ICERIK]
                : [User::ROL_ICERIK, User::ROL_BASIN];

            $kullanici = $this->hesabiHazirla($veri['eposta'], [
                'name' => $veri['ad_soyad'],
                'telefon' => $this->telefonBicimle($veri['telefon']),
                'adres' => $veri['adres'],
                'il' => $veri['il'],
                'ilce' => $veri['ilce'],
                'kurum_id' => $kurum?->id,
            ], $rol, $eskiRol);

            $basvuru = Basvuru::create([
                'tur' => $tur,
                'durum' => BasvuruDurumu::Taslak,
                'kullanici_id' => $kullanici->id,
                'kurum_id' => $kurum?->id,
                'kurum_baslatti' => $kurumBaslatti,
                'basvuran_ad' => $veri['ad_soyad'],
                'basvuran_eposta' => $veri['eposta'],
                'basvuran_telefon' => $this->telefonBicimle($veri['telefon']),
                'form_verisi' => array_filter([
                    'basin_karti_var' => $veri['basin_karti_var'],
                    'sigorta_212_var' => $veri['sigorta_212_var'] ?? null,
                    'calisma_yili' => $veri['calisma_yili'] ?? null,
                    'sosyal_medya' => array_filter($veri['sosyal_medya'] ?? []) ?: null,
                    'kvkk_onay_at' => now()->toIso8601String(),
                ], fn ($d) => $d !== null),
            ]);

            $this->denetim->yaz('basvuru.olusturuldu', $basvuru,
                yeni: [
                    'tur' => $tur->value,
                    'kurum' => $kurum?->resmi_unvan,
                    'yol' => $kurumBaslatti ? 'davet' : 'kendi',
                    'yeniden' => ! $kullanici->wasRecentlyCreated,
                ],
                aktorTip: 'sistem');

            $this->basvuranaHaberVer($kullanici, $basvuru);

            return $basvuru;
        });
    }

    /**
     * Başvuranın hesabı. VARSA TEKRAR KULLANILIR — reddedilen ya da kurumundan
     * ayrılan kişi aynı e-postayla yeniden başvurabilsin diye (ikinci hesap
     * açılmaz, e-posta zaten benzersiz). Yeniden başvuruda ayrılış işareti
     * kalkar, iletişim bilgileri formdaki güncel değerlerle yenilenir ve
     * değişiklik denetim kaydına eski → yeni olarak düşer.
     *
     * ⚠️ Uygunluk BURADA da bakılır: davet yolunda form kuralı e-posta
     * alanını hiç doğrulamıyor (ad/e-posta davetten geliyor).
     */
    private function hesabiHazirla(string $eposta, array $alanlar, string $rol, ?string $eskiRol = null): User
    {
        $kullanici = $this->uygunluk->hesapBul($eposta);
        $this->uygunluk->dogrula($kullanici);

        if ($kullanici === null) {
            $kullanici = User::create($alanlar + [
                'email' => $eposta,
                'password' => Hash::make(Str::random(64)),   // yer tutucu; kullanıcı kendi belirler
                'aktif' => true,
            ]);
        } else {
            $eski = collect($alanlar)
                ->keys()
                ->mapWithKeys(fn (string $alan) => [$alan => $kullanici->getAttribute($alan)])
                ->all();

            if ($kullanici->trashed()) {
                $kullanici->restore();
            }

            // Ayrılış işareti KALKAR: kişi yeniden süreçte ve evrak yükleyebilmek
            // için panele girebilmeli. Eski akreditasyonu iptal olarak KALIR.
            $kullanici->forceFill($alanlar + ['aktif' => true, 'ayrildi_at' => null])->save();

            $this->denetim->yaz('hesap.yeniden_basvuru', $kullanici,
                eski: $eski, yeni: $alanlar, aktorTip: 'sistem');
        }

        // Tür değiştiyse eski tür rolü kalmasın; kurum yetkililiği gibi başka
        // roller korunur (syncRoles hepsini silerdi).
        if ($eskiRol !== null) {
            $kullanici->removeRole($eskiRol);
        }

        $kullanici->assignRole($rol);

        return $kullanici;
    }

    /**
     * Kurum kaydı. Yetkilinin daha önce reddedilmiş bir KURUM başvurusu varsa
     * aynı kurum kaydı güncellenir; her denemede yeni bir kurum satırı açılmaz.
     */
    private function kurumuHazirla(User $kullanici, array $veri): Kurum
    {
        $onceki = Kurum::query()
            ->whereIn('id', $kullanici->basvurular()
                ->where('tur', BasvuruTuru::Kurum->value)
                ->whereNotNull('kurum_id')
                ->pluck('kurum_id'))
            ->where('akreditasyon_durumu', '!=', 'akredite')
            ->latest('id')
            ->first();

        if ($onceki) {
            $onceki->update($veri);

            return $onceki;
        }

        return Kurum::create($veri);
    }

    /**
     * Hesabı henüz etkin değilse şifre belirleme bağlantısı, etkinse
     * "yeni başvurunuz alındı" bilgisi gider. Aktivasyon bağlantısını hazır
     * hesaba göndermek gereksiz bir şifre sıfırlama kapısı açardı.
     */
    private function basvuranaHaberVer(User $kullanici, Basvuru $basvuru): bool
    {
        if ($kullanici->email_verified_at === null) {
            $kullanici->notify(new HesapAktivasyonu);

            return $this->aktivasyonGonderildi = true;
        }

        $kullanici->notify(new YenidenBasvuruAlindi($basvuru));

        return $this->aktivasyonGonderildi = false;
    }

    /** @return Collection<int, Kurum> */
    private function akrediteKurumlar()
    {
        return Kurum::query()
            ->where('akreditasyon_durumu', 'akredite')
            ->orderBy('resmi_unvan')
            ->get(['ulid', 'resmi_unvan']);
    }

    public function gonderildi(): View
    {
        abort_unless(session()->has('eposta'), 404);

        return view('basvuru.gonderildi', [
            'eposta' => session('eposta'),
            'aktivasyon' => (bool) session('aktivasyon', true),
        ]);
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
