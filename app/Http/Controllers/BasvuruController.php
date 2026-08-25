<?php

namespace App\Http\Controllers;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Exceptions\EvrakAlinamadi;
use App\Http\Requests\BireyselBasvuruIstegi;
use App\Http\Requests\KurumBasvuruIstegi;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Models\Kurum;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruEvrakAlici;
use App\Servisler\BasvuruUygunlugu;
use App\Servisler\DavetAkisi;
use App\Servisler\DenetimYazici;
use App\Support\Telefon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Kamuya açık başvuru -- Plan v1.0 md.5.1, Revizyon md.1.
 *
 * 🔑 Başvuru TEK ADIMDIR: kurum/kişi bilgileri, evraklar ve KVKK onayları aynı
 * formda gelir, gönderim anında başvuru doğrudan inceleme kuyruğuna düşer.
 * Hesap AÇILMAZ -- onaylanmayan kişinin kullanıcı kaydı hiç doğmaz (md.3.2);
 * iletişim bilgisi o güne kadar başvurunun üstünde durur.
 */
class BasvuruController extends Controller
{
    public function __construct(
        private DenetimYazici $denetim,
        private DavetAkisi $davetAkisi,
        private BasvuruUygunlugu $uygunluk,
        private BasvuruEvrakAlici $evrakAlici,
        private BasvuruAkisi $akis,
    ) {}

    public function secim(): View
    {
        return view('basvuru.secim');
    }

    public function kurumFormu(): View
    {
        return view('basvuru.kurum', [
            'evrakTurleri' => EvrakTuru::turIcin(BasvuruTuru::Kurum),
        ]);
    }

    public function kurumKaydet(KurumBasvuruIstegi $istek): RedirectResponse
    {
        $veri = $istek->validated();

        try {
            $this->basvuruyuAl(
                fn () => $this->kurumBasvurusuOlustur($veri),
                $istek->file('evraklar', []),
                BasvuruTuru::Kurum,
            );
        } catch (EvrakAlinamadi $e) {
            throw ValidationException::withMessages([$e->alan() => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        return redirect()->route('basvuru.gonderildi')->with('eposta', $veri['yetkili_eposta']);
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
            'evrakTurleri' => EvrakTuru::turIcin($tur),
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
            $this->basvuruyuAl(
                fn () => $this->bireyselOlustur($tur, $kurum, $veri, kurumBaslatti: false),
                $istek->file('evraklar', []),
                $tur,
            );
        } catch (EvrakAlinamadi $e) {
            throw ValidationException::withMessages([$e->alan() => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        return redirect()->route('basvuru.gonderildi')->with('eposta', $veri['eposta']);
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
            'evrakTurleri' => EvrakTuru::turIcin(BasvuruTuru::BasinMensubu),
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
            $this->basvuruyuAl(
                function () use ($davet, $veri) {
                    $basvuru = $this->bireyselOlustur(
                        BasvuruTuru::BasinMensubu, $davet->kurum, $veri, kurumBaslatti: true,
                    );

                    // Davetin tüketilmesi başvuruyla AYNI işlemde: başvuru geri
                    // sararsa davet de yanmasın, ikisi birden olsun.
                    $davet->update(['kullanildi_at' => now(), 'basvuru_id' => $basvuru->id]);

                    return $basvuru;
                },
                $istek->file('evraklar', []),
                BasvuruTuru::BasinMensubu,
            );
        } catch (EvrakAlinamadi $e) {
            throw ValidationException::withMessages([$e->alan() => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['genel' => $e->getMessage()]);
        }

        return redirect()->route('basvuru.gonderildi')->with('eposta', $davet->eposta);
    }

    /**
     * Başvurunun tamamı TEK işlemde: kayıt, evraklar ve gönderim.
     *
     * 💣 Evrak dosyaları diske işlemden ÖNCE yazılır; geri sarma onları
     * silmez. Bu yüzden her hata yolunda yazılanlar elle temizlenir --
     * yarım kalan başvurudan diskte yetim dosya kalmaz.
     *
     * @param  callable(): Basvuru  $olustur
     * @param  array<int|string, mixed>  $dosyalar
     */
    private function basvuruyuAl(callable $olustur, array $dosyalar, BasvuruTuru $tur): Basvuru
    {
        try {
            return DB::transaction(function () use ($olustur, $dosyalar, $tur) {
                $basvuru = $olustur();

                $this->evrakAlici->hepsiniAl($basvuru, $dosyalar, EvrakTuru::turIcin($tur));

                /*
                 * Gönderim TEK KAPIDAN: durum geçişi, denetim kaydı, "başvurunuz
                 * alındı" e-postası ve kurum teyidi kuralı BasvuruAkisi'nde.
                 * Zorunlu evrak eksikse burada durur -- form kuralı atlansa bile
                 * yarım başvuru kuyruğa düşmez.
                 */
                $this->akis->gonder($basvuru);

                return $basvuru;
            });
        } catch (Throwable $e) {
            $this->evrakAlici->yazilanlariSil();

            throw $e;
        }
    }

    /** @param array<string, mixed> $veri */
    private function kurumBasvurusuOlustur(array $veri): Basvuru
    {
        $eposta = $veri['yetkili_eposta'];

        $this->uygunluk->epostaIcinDogrula($eposta, BasvuruTuru::Kurum);

        $kurum = $this->kurumuHazirla($eposta, [
            'resmi_unvan' => $veri['resmi_unvan'],
            'adres' => $veri['adres'],
            'il' => $veri['il'],
            'ilce' => $veri['ilce'],
            'telefon' => Telefon::e164($veri['kurum_telefon'], $veri['kurum_telefon_ulke']),
            'eposta' => $veri['kurum_eposta'],
            'vergi_dairesi' => $veri['vergi_dairesi'],
            'vergi_no' => $veri['vergi_no'],
            'calisan_araligi' => $veri['calisan_araligi'],
            'yayin_platformlari' => array_values($veri['yayin_platformlari']),
            'sosyal_medya' => array_filter($veri['sosyal_medya'] ?? []),
            'akreditasyon_durumu' => 'beklemede',
        ]);

        $basvuru = Basvuru::create([
            'tur' => BasvuruTuru::Kurum,
            'durum' => BasvuruDurumu::Taslak,
            // Hesap ONAY anında açılır (Revizyon md.2.1); iletişim bilgisi
            // o güne kadar başvurunun üstünde durur.
            'kullanici_id' => null,
            'kurum_id' => $kurum->id,
            'basvuran_ad' => $veri['yetkili_ad'],
            'basvuran_eposta' => $eposta,
            'basvuran_telefon' => Telefon::e164($veri['yetkili_telefon'], $veri['yetkili_telefon_ulke']),
            'form_verisi' => [
                'yetkili_ad' => $veri['yetkili_ad'],
                'kvkk_onay_at' => now()->toIso8601String(),
            ],
        ]);

        $this->denetim->yaz('basvuru.olusturuldu', $basvuru,
            yeni: ['tur' => 'kurum', 'kurum' => $kurum->resmi_unvan],
            aktorTip: 'sistem');

        return $basvuru;
    }

    /**
     * İki bireysel yolun ortak gövdesi: kurum bağı ve teyit gerekliliği
     * dışında aynı.
     *
     * @param  array<string, mixed>  $veri
     */
    private function bireyselOlustur(BasvuruTuru $tur, ?Kurum $kurum, array $veri, bool $kurumBaslatti): Basvuru
    {
        $this->uygunluk->epostaIcinDogrula($veri['eposta'], $tur);

        $basvuru = Basvuru::create([
            'tur' => $tur,
            'durum' => BasvuruDurumu::Taslak,
            'kullanici_id' => null,
            'kurum_id' => $kurum?->id,
            'kurum_baslatti' => $kurumBaslatti,
            'basvuran_ad' => $veri['ad_soyad'],
            'basvuran_eposta' => $veri['eposta'],
            'basvuran_telefon' => Telefon::e164($veri['telefon'], $veri['telefon_ulke']),
            // 🔑 Kişisel bilgiler hesap yerine BAŞVURUDA durur; onay anında
            // HesapAcici bunları açtığı hesaba taşır.
            'form_verisi' => array_filter([
                'adres' => $veri['adres'],
                'il' => $veri['il'],
                'ilce' => $veri['ilce'],
                'basin_karti_var' => $veri['basin_karti_var'],
                'sigorta_212_var' => $veri['sigorta_212_var'] ?? null,
                'calisma_yili' => $veri['calisma_yili'] ?? null,
                'sosyal_medya' => array_filter($veri['sosyal_medya'] ?? []) ?: null,
                'kvkk_onay_at' => now()->toIso8601String(),
            ], fn ($deger) => $deger !== null),
        ]);

        $this->denetim->yaz('basvuru.olusturuldu', $basvuru,
            yeni: [
                'tur' => $tur->value,
                'kurum' => $kurum?->resmi_unvan,
                'yol' => $kurumBaslatti ? 'davet' : 'kendi',
            ],
            aktorTip: 'sistem');

        return $basvuru;
    }

    /**
     * Kurum kaydı. Aynı yetkili daha önce başvurup reddedildiyse o kurum kaydı
     * güncellenir; her denemede yeni bir kurum satırı açılmaz. Hesap artık
     * başvuru anında olmadığı için bağ E-POSTA üzerinden kurulur.
     *
     * @param  array<string, mixed>  $veri
     */
    private function kurumuHazirla(string $eposta, array $veri): Kurum
    {
        // Aynı arama vergi no tekillik kuralında da kullanılıyor: tek kaynak.
        $onceki = Kurum::yetkilininOncekiKurumu($eposta);

        if ($onceki) {
            $onceki->update($veri);

            return $onceki;
        }

        return Kurum::create($veri);
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
            // Panelsiz düzeltmeden gelindiyse metin farklı.
            'duzeltme' => (bool) session('duzeltme', false),
        ]);
    }
}
