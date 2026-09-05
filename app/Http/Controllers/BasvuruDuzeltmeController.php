<?php

namespace App\Http\Controllers;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\BasvuruBileti;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\DuzeltmeUygulayici;
use App\Servisler\EvrakYukleyici;
use App\Support\DuzeltmeAlanlari;
use App\Support\WebAdresi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Eksik evrak düzeltmesi -- PANELSİZ (Revizyon md.3.4).
 *
 * Başvuranın hesabı yoktur; kimlik yerine e-postayla giden tek kullanımlık,
 * süreli bilet geçer. Sayfa YALNIZCA yetkilinin işaretlediği alanları açar.
 */
class BasvuruDuzeltmeController extends Controller
{
    public function __construct(
        private BasvuruBiletiAkisi $biletAkisi,
        private BasvuruAkisi $akis,
        private EvrakYukleyici $yukleyici,
        private DuzeltmeUygulayici $uygulayici,
    ) {}

    public function form(string $token): View
    {
        $bilet = $this->bileti($token);

        return view('basvuru.duzelt', $this->ekran($bilet, $token));
    }

    public function kaydet(Request $istek, string $token): RedirectResponse
    {
        $bilet = $this->bileti($token);
        $basvuru = $bilet->basvuru;
        $turler = $this->istenenEvrakTurleri($bilet);
        $duzeltme = $basvuru->acikDuzeltme();
        $izinli = $basvuru->duzeltilebilirAlanlar();
        $ekTalepler = $duzeltme !== null ? ($duzeltme->ek_talepler ?? []) : [];

        $this->baglantiAlanlariniDuzelt($istek, $basvuru, $izinli);

        $veri = $istek->validate(
            $this->kurallar($turler, $basvuru, $izinli, $ekTalepler),
            [
                'evraklar.*.max' => 'Dosya çok büyük.',
                'evraklar.*.file' => 'Geçerli bir dosya seçin.',
                'ek.*.max' => 'Dosya çok büyük.',
            ],
            $this->alanAdlari($turler, $basvuru, $izinli, $ekTalepler),
        );

        /*
         * 🪤 Yüklemeler TEK bir dış işleme sarılmaz: EvrakYukleyici dosyayı
         * diske kendi işleminden ÖNCE yazıyor. Dıştan geri sarmak veritabanını
         * temizler ama dosyayı diskte bırakırdı. Her evrak kendi işleminde
         * yazılır; gönderim reddedilse bile yüklenen evrak başvuranın üstünde
         * kalır ve eksiği tamamlayınca tekrar denenebilir.
         */
        /*
         * 🔑 Yüklenen evrak da TURA KAYDEDİLİR (Yusuf revizyonu md.4:
         * "kullanıcının neyi yeni eklediğini ... gösteren bir kayıt").
         * Eskiden yalnızca dosya yazılıyordu; geçmişte "istendi" görünüyor
         * ama başvuranın gerçekten yükleyip yüklemediği görünmüyordu.
         */
        $evrakDegisimleri = [];
        /** @var Collection<int, Evrak> $oncekiler */
        $oncekiler = $basvuru->evraklar->keyBy('evrak_turu_id');

        foreach ($turler as $tur) {
            $dosya = $istek->file("evraklar.{$tur->id}");

            if ($dosya === null) {
                continue;
            }

            // Eski dosyanın adı yükleme ÖNCESİ okunur: `yukle()` onu arşivler.
            $onceki = $oncekiler[$tur->id] ?? null;

            try {
                $evrak = $this->yukleyici->yukle($basvuru, $tur, $dosya);
            } catch (Throwable $e) {
                throw ValidationException::withMessages([
                    "evraklar.{$tur->id}" => $e->getMessage(),
                ]);
            }

            $evrakDegisimleri[DuzeltmeAlanlari::EVRAK_ONEK.$tur->kod] = [
                'eski' => $onceki?->orijinal_ad,
                'yeni' => $evrak->orijinal_ad,
            ];
        }

        // ── Ek talepler: listemizde olmayan belge / yazılı bilgi ──
        $ekDegisimler = $this->ekTalepleriIsle($istek, $basvuru, $ekTalepler);

        // ── Veri alanları: yalnızca İŞARETLİ olanlar, öncesi/sonrası kayda geçer ──
        try {
            $degisimler = $this->uygulayici->yaz($basvuru, $veri['alan'] ?? [], $izinli);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['genel' => $e->getMessage()]);
        }

        $aciklama = $istek->string('aciklama')->trim()->toString() ?: null;

        if (filled($aciklama)) {
            $basvuru->update([
                'form_verisi' => ($basvuru->form_verisi ?? []) + [
                    'duzeltme_aciklamasi' => mb_substr($aciklama, 0, 2000),
                ],
            ]);
        }

        $belgeTalebi = $duzeltme?->belgeTalebiMi() ?? false;

        /*
         * 🔑 BELGE TALEBİ AYRI KAPANIR. `gonder()` başvuruyu
         * `yeniden_inceleme`ye sokar, zorunlu evrak denetimini baştan
         * çalıştırır ve yeni bir karar bekler. Onaylanmış bir başvuruda
         * istenen bu değil: belge geldi, dosyaya girdi, kart yerinde duruyor.
         */
        if ($belgeTalebi) {
            $this->akis->belgeTalebiniKapat(
                $duzeltme, $degisimler + $ekDegisimler + $evrakDegisimleri, $aciklama,
            );

            $this->biletAkisi->tuket($bilet);

            return redirect()->route('basvuru.gonderildi')
                ->with('eposta', $basvuru->basvuranEpostasi())
                ->with('belge_talebi', true);
        }

        /*
         * 🔑 Değişiklikler ÖNCE kaydedilir, tur SONRA kapanır. `gonder()`
         * başarısız olursa (zorunlu evrak hâlâ eksik) başvuranın düzelttiği
         * alanlar kayıtlı kalır, tur da AÇIK kalır: aynı bağlantıya dönüp
         * eksiği tamamlayabilir.
         */
        if ($duzeltme !== null) {
            $this->akis->duzeltmeyiKaydet($duzeltme, $degisimler + $ekDegisimler + $evrakDegisimleri, $aciklama);
        }

        try {
            $this->akis->gonder($basvuru);
        } catch (Throwable $e) {
            // En sık sebep: zorunlu evrak hâlâ eksik. Alan bazlı hata göster,
            // 500 sayfası değil.
            throw ValidationException::withMessages(['genel' => $e->getMessage()]);
        }

        if ($duzeltme !== null) {
            $this->akis->duzeltmeyiKapat($duzeltme);
        }

        $this->biletAkisi->tuket($bilet);

        return redirect()->route('basvuru.gonderildi')
            ->with('eposta', $basvuru->basvuranEpostasi())
            ->with('duzeltme', true);
    }

    /**
     * Bileti çözer. Geçersiz, süresi dolmuş, kullanılmış ya da başvurusu artık
     * düzeltme beklemeyen bilet 410 ile kapanır -- 404 değil: bağlantının
     * VARDI ama ARTIK GEÇERSİZ olduğu bilgisi başvurana yardımcı olur.
     */
    private function bileti(string $token): BasvuruBileti
    {
        $bilet = $this->biletAkisi->tokenlaBul($token)
            ?? abort(410, 'Bu düzeltme bağlantısı geçersiz veya süresi dolmuş. Kulüple iletişime geçerek yeni bağlantı isteyebilirsiniz.');

        /*
         * 🔑 İKİ AYRI BEKLEME HÂLİ var (Cüneyt Bey revizyonu 05.09.2026):
         *   - karar öncesi düzeltme  -> başvuru `eksik_evrak` durumundadır
         *   - karar sonrası belge talebi -> başvuru `onaylandi` KALIR; durum
         *     sorulsaydı akredite kişinin bağlantısı 410 ile kapanırdı.
         * Ortak ölçüt durum değil, YANITLANMAMIŞ TUR.
         */
        abort_unless(
            $bilet->basvuru?->durum === BasvuruDurumu::EksikEvrak
                || $bilet->basvuru?->acikBelgeTalebi() !== null,
            410,
            'Bu başvuru için belge beklenmiyor. Başvurunuz incelemede olabilir.',
        );

        return $bilet;
    }

    /** @return array<string, mixed> */
    private function ekran(BasvuruBileti $bilet, string $token): array
    {
        $basvuru = $bilet->basvuru;
        $turler = $this->istenenEvrakTurleri($bilet);
        $notlar = $basvuru->duzeltme_notlari ?? [];
        $duzeltme = $basvuru->acikDuzeltme();

        /*
         * 💀 ESKİDEN: işaretli veri alanları yalnızca LİSTELENİYORDU ve
         * başvurana tek bir serbest açıklama kutusu açılıyordu. Kişi doğrusunu
         * yazıyor, başvuru yeniden gönderiliyor, YANLIŞ VERİ HÂLÂ YANLIŞ
         * kalıyordu (Yusuf revizyonu 25.08.2026). Artık her işaretli alanın
         * kendi girdisi var ve önceki değeri yanında görünüyor.
         */
        $duzeltilebilir = [];
        $notOlanlar = [];

        foreach ($notlar as $anahtar => $aciklama) {
            if (DuzeltmeAlanlari::evrakMi($anahtar) || DuzeltmeAlanlari::ekMi($anahtar)) {
                continue;
            }

            $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

            // Tanımsız (eski çıplak ad) ya da düzeltilemez alan: yalnızca not.
            if ($tanim === null || ! $tanim['duzeltilebilir']) {
                $notOlanlar[$anahtar] = $aciklama;

                continue;
            }

            $duzeltilebilir[] = [
                'anahtar' => $anahtar,
                'girdi' => DuzeltmeUygulayici::girdiAdi($anahtar),
                'etiket' => $tanim['etiket'],
                'tip' => $tanim['tip'],
                'aciklama' => $aciklama,
                'deger' => $this->uygulayici->deger($basvuru, $anahtar),
                'gosterim' => $this->uygulayici->goster($basvuru, $anahtar),
            ];
        }

        return [
            'bilet' => $bilet,
            'basvuru' => $basvuru,
            'token' => $token,
            'duzeltme' => $duzeltme,
            'evrakTurleri' => $turler,
            'duzeltilebilirAlanlar' => $duzeltilebilir,
            // Düzeltilemeyen işaretler (E-posta, Kurum) yalnızca gösterilir;
            // başvuran açıklama kutusundan yanıt verir.
            'veriNotlari' => $notOlanlar,
            'ekTalepler' => $duzeltme !== null ? ($duzeltme->ek_talepler ?? []) : [],
            'yuklenmisEvraklar' => $basvuru->evraklar->keyBy('evrak_turu_id'),
            'ekYuklenmisler' => $basvuru->evraklar->whereNotNull('ek_etiket')->keyBy('ek_etiket'),
            // Yanıtlanmış önceki turlar: "neyi yeni ekledim, öncesi neydi".
            'gecmisTurlar' => $basvuru->duzeltmeler()->whereNotNull('yanit_at')->get(),
        ];
    }

    /**
     * Yetkilinin işaretlediği EVRAK türleri. İşaret yoksa (yalnızca veri alanı
     * istenmişse) hiçbir yükleme kutusu açılmaz.
     *
     * @return Collection<int, EvrakTuru>
     */
    private function istenenEvrakTurleri(BasvuruBileti $bilet): Collection
    {
        $isaretli = $bilet->basvuru->duzeltilebilirAlanlar();

        return EvrakTuru::turIcin($bilet->basvuru->tur)
            ->filter(fn (EvrakTuru $tur) => DuzeltmeAlanlari::evrakIsteniyorMu($tur, $isaretli))
            ->values();
    }

    /**
     * @param  Collection<int, EvrakTuru>  $turler
     * @param  array<int, string>  $izinli
     * @param  array<int, array<string, string>>  $ekTalepler
     * @return array<string, mixed>
     */
    private function kurallar(Collection $turler, Basvuru $basvuru, array $izinli, array $ekTalepler): array
    {
        $kurallar = [
            'evraklar' => ['array'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($turler as $tur) {
            // Boyut sınırı evrak türünden; içerik doğrulaması EvrakYukleyici'de.
            $kurallar["evraklar.{$tur->id}"] = ['nullable', 'file', 'max:'.$tur->maks_boyut_kb];
        }

        foreach ($ekTalepler as $ek) {
            $ad = 'ek.'.str_replace(':', '_', $ek['anahtar']);

            $kurallar[$ad] = ($ek['tip'] ?? 'dosya') === 'dosya'
                ? ['nullable', 'file', 'max:8192']
                : ['nullable', 'string', 'max:1000'];
        }

        return $kurallar + $this->uygulayici->kurallar($basvuru, $izinli);
    }

    /**
     * Hata mesajlarında ham girdi adı ("alan.veri_telefon") değil, insanca
     * etiket görünsün.
     *
     * @param  Collection<int, EvrakTuru>  $turler
     * @param  array<int, string>  $izinli
     * @param  array<int, array<string, string>>  $ekTalepler
     * @return array<string, string>
     */
    private function alanAdlari(Collection $turler, Basvuru $basvuru, array $izinli, array $ekTalepler): array
    {
        $adlar = $turler->mapWithKeys(fn (EvrakTuru $tur) => ["evraklar.{$tur->id}" => $tur->ad])->all();

        foreach ($ekTalepler as $ek) {
            $adlar['ek.'.str_replace(':', '_', $ek['anahtar'])] = $ek['etiket'];
        }

        foreach ($izinli as $anahtar) {
            $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

            if ($tanim === null) {
                continue;
            }

            $ad = 'alan.'.DuzeltmeUygulayici::girdiAdi($anahtar);

            $adlar[$ad] = $tanim['etiket'];
            $adlar[$ad.'_il'] = 'il';
            $adlar[$ad.'_ilce'] = 'ilçe';
            $adlar[$ad.'_ulke'] = $tanim['etiket'].' ülke kodu';
        }

        return $adlar;
    }

    /**
     * Ek talepleri işler. Dosya olanlar `ek_belge` türüne, yazılı olanlar
     * doğrudan tur kaydına yazılır.
     *
     * @param  array<int, array<string, string>>  $ekTalepler
     * @return array<string, array{eski: mixed, yeni: mixed}>
     */
    private function ekTalepleriIsle(Request $istek, Basvuru $basvuru, array $ekTalepler): array
    {
        if ($ekTalepler === []) {
            return [];
        }

        $degisimler = [];
        $ekTuru = EvrakTuru::where('kod', 'ek_belge')->first();

        foreach ($ekTalepler as $ek) {
            $ad = 'ek.'.str_replace(':', '_', $ek['anahtar']);

            if (($ek['tip'] ?? 'dosya') === 'metin') {
                if (filled($metin = $istek->string($ad)->trim()->toString())) {
                    $degisimler[$ek['anahtar']] = ['eski' => null, 'yeni' => $metin];
                }

                continue;
            }

            if (($dosya = $istek->file($ad)) === null) {
                continue;
            }

            if ($ekTuru === null) {
                throw ValidationException::withMessages([
                    'genel' => 'Ek belge türü tanımlı değil. Kulüple iletişime geçin.',
                ]);
            }

            try {
                $evrak = $this->yukleyici->yukle($basvuru, $ekTuru, $dosya, $ek['etiket']);
            } catch (Throwable $e) {
                throw ValidationException::withMessages([$ad => $e->getMessage()]);
            }

            $degisimler[$ek['anahtar']] = ['eski' => null, 'yeni' => $evrak->orijinal_ad];
        }

        return $degisimler;
    }

    /**
     * Bağlantı alanlarında şemayı SUNUCUDA tamamlar -- Cüneyt Bey revizyonu
     * (03.09.2026): "http vs yazmaya zorlamamalıyız."
     *
     * 🔑 Kamu formundaki kuralın aynısı düzeltme ekranında da geçerli olmalı;
     * yoksa yetkili "Yayın kanalları"nı işaretlediğinde başvuran aynı duvara
     * ikinci kez toslardı.
     *
     * @param  array<int, string>  $izinli
     */
    private function baglantiAlanlariniDuzelt(Request $istek, Basvuru $basvuru, array $izinli): void
    {
        $alan = $istek->input('alan');

        if (! is_array($alan)) {
            return;
        }

        $degisti = false;

        foreach ($izinli as $anahtar) {
            $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

            if ($tanim === null || ! in_array($tanim['tip'], ['sosyal', 'platformlar'], true)) {
                continue;
            }

            $ad = DuzeltmeUygulayici::girdiAdi($anahtar);

            if (! isset($alan[$ad]) || ! is_array($alan[$ad])) {
                continue;
            }

            /*
             * 🪤 İki ayrı şekil var: `sosyal` düz adres listesi, `platformlar`
             * ise satır satır ['ad' => …, 'url' => …]. Kamu formundaki
             * (KurumBasvuruIstegi) davranışın aynısı burada da geçerli olmalı.
             */
            $alan[$ad] = $tanim['tip'] === 'platformlar'
                ? array_map(
                    fn ($satir) => is_array($satir)
                        ? ['url' => WebAdresi::duzelt($satir['url'] ?? null)] + $satir
                        : WebAdresi::duzelt($satir),
                    $alan[$ad],
                )
                : WebAdresi::dizi($alan[$ad]);

            $degisti = true;
        }

        if ($degisti) {
            $istek->merge(['alan' => $alan]);
        }
    }
}
