<?php

namespace App\Http\Controllers;

use App\Enums\BasvuruDurumu;
use App\Models\BasvuruBileti;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\EvrakYukleyici;
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

        $istek->validate(
            $this->kurallar($turler),
            [
                'evraklar.*.max' => 'Dosya çok büyük.',
                'evraklar.*.file' => 'Geçerli bir dosya seçin.',
            ],
            $turler->mapWithKeys(fn (EvrakTuru $tur) => ["evraklar.{$tur->id}" => $tur->ad])->all(),
        );

        /*
         * 🪤 Yüklemeler TEK bir dış işleme sarılmaz: EvrakYukleyici dosyayı
         * diske kendi işleminden ÖNCE yazıyor. Dıştan geri sarmak veritabanını
         * temizler ama dosyayı diskte bırakırdı. Her evrak kendi işleminde
         * yazılır; gönderim reddedilse bile yüklenen evrak başvuranın üstünde
         * kalır ve eksiği tamamlayınca tekrar denenebilir.
         */
        foreach ($turler as $tur) {
            $dosya = $istek->file("evraklar.{$tur->id}");

            if ($dosya === null) {
                continue;
            }

            try {
                $this->yukleyici->yukle($basvuru, $tur, $dosya);
            } catch (Throwable $e) {
                throw ValidationException::withMessages([
                    "evraklar.{$tur->id}" => $e->getMessage(),
                ]);
            }
        }

        if (filled($aciklama = $istek->string('aciklama')->trim()->toString())) {
            $basvuru->update([
                'form_verisi' => ($basvuru->form_verisi ?? []) + [
                    'duzeltme_aciklamasi' => mb_substr($aciklama, 0, 2000),
                ],
            ]);
        }

        try {
            $this->akis->gonder($basvuru);
        } catch (Throwable $e) {
            // En sık sebep: zorunlu evrak hâlâ eksik. Alan bazlı hata göster,
            // 500 sayfası değil.
            throw ValidationException::withMessages(['genel' => $e->getMessage()]);
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

        abort_unless(
            $bilet->basvuru?->durum === BasvuruDurumu::EksikEvrak,
            410,
            'Bu başvuru için düzeltme beklenmiyor. Başvurunuz incelemede olabilir.',
        );

        return $bilet;
    }

    /** @return array<string, mixed> */
    private function ekran(BasvuruBileti $bilet, string $token): array
    {
        $basvuru = $bilet->basvuru;
        $turler = $this->istenenEvrakTurleri($bilet);

        return [
            'bilet' => $bilet,
            'basvuru' => $basvuru,
            'token' => $token,
            'evrakTurleri' => $turler,
            // Evrak olmayan işaretler (Telefon, Vergi no...) yalnızca gösterilir;
            // başvuran açıklama kutusundan yanıt verir.
            'veriNotlari' => collect($basvuru->duzeltme_notlari ?? [])
                ->reject(fn ($aciklama, $alan) => $turler->contains('ad', $alan))
                ->all(),
            'yuklenmisEvraklar' => $basvuru->evraklar->keyBy('evrak_turu_id'),
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
            ->filter(fn (EvrakTuru $tur) => in_array($tur->ad, $isaretli, true))
            ->values();
    }

    /**
     * @param  Collection<int, EvrakTuru>  $turler
     * @return array<string, mixed>
     */
    private function kurallar(Collection $turler): array
    {
        $kurallar = [
            'evraklar' => ['array'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($turler as $tur) {
            // Boyut sınırı evrak türünden; içerik doğrulaması EvrakYukleyici'de.
            $kurallar["evraklar.{$tur->id}"] = ['nullable', 'file', 'max:'.$tur->maks_boyut_kb];
        }

        return $kurallar;
    }
}
