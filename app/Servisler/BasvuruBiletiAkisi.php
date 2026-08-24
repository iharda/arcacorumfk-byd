<?php

namespace App\Servisler;

use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\BasvuruBileti;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Eksik evrak düzeltme bileti -- Revizyon md.3.3.
 *
 * Yetkili "eksik evrak" dediğinde başvurana geçici bir bağlantı gider; kişi
 * hesaba, şifreye, panele gerek kalmadan yalnızca işaretli alanları düzeltip
 * başvurusunu yeniden gönderir.
 *
 * 🔒 Ham token SAKLANMAZ; yalnızca sha256 hash'i tutulur ve e-postayla bir kez
 * gönderilir. Kaybolursa yenisi üretilir, eskisi geçersiz olur.
 */
class BasvuruBiletiAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    /**
     * Yeni bilet üretir ve HAM token'ı döndürür.
     *
     * Aynı başvurunun bekleyen biletleri iptal edilir: tek anda tek geçerli
     * bağlantı olur, eski e-postadaki link ölür.
     */
    public function uret(Basvuru $basvuru, string $amac = 'eksik_evrak'): string
    {
        return DB::transaction(function () use ($basvuru, $amac) {
            $basvuru->biletler()
                ->whereNull('kullanildi_at')
                ->whereNull('iptal_at')
                ->update(['iptal_at' => now()]);

            $token = Str::random(48);

            /** @var BasvuruBileti $bilet */
            $bilet = $basvuru->biletler()->create([
                'olusturan_id' => Auth::id(),
                'token_hash' => BasvuruBileti::tokenHash($token),
                'amac' => $amac,
                'gecerlilik_bitis' => now()->addDays($this->gecerlilikGun()),
            ]);

            $this->denetim->yaz('basvuru_bileti.olusturuldu', $bilet, yeni: [
                'amac' => $amac,
                'gecerlilik_bitis' => $bilet->gecerlilik_bitis->toDateTimeString(),
            ]);

            return $token;
        });
    }

    /**
     * Bağlantı ulaşmadıysa yenisini üretir. Eski bilet iptal olur; süresi
     * yeniden başlar.
     *
     * @return string yeni ham token
     */
    public function yenidenGonder(Basvuru $basvuru): string
    {
        $bilet = $basvuru->acikBilet();

        // Açık bilet yoksa (süresi dolmuş ya da hiç üretilmemiş) yenisi doğar.
        if ($bilet === null) {
            return $this->uret($basvuru);
        }

        $token = Str::random(48);

        DB::transaction(function () use ($bilet, $token) {
            $bilet->update([
                'token_hash' => BasvuruBileti::tokenHash($token),
                'gecerlilik_bitis' => now()->addDays($this->gecerlilikGun()),
                'gonderim_sayisi' => $bilet->gonderim_sayisi + 1,
            ]);

            $this->denetim->yaz('basvuru_bileti.yeniden_gonderildi', $bilet, yeni: [
                'gonderim_sayisi' => $bilet->gonderim_sayisi,
            ]);
        });

        return $token;
    }

    public function iptalEt(BasvuruBileti $bilet): void
    {
        DB::transaction(function () use ($bilet) {
            $bilet->update(['iptal_at' => now()]);

            $this->denetim->yaz('basvuru_bileti.iptal', $bilet);
        });
    }

    /** Düzeltme gönderildi: bilet ölür, aynı bağlantı ikinci kez açılmaz. */
    public function tuket(BasvuruBileti $bilet): void
    {
        DB::transaction(function () use ($bilet) {
            $bilet->update(['kullanildi_at' => now()]);

            $this->denetim->yaz('basvuru_bileti.kullanildi', $bilet, aktorTip: 'sistem');
        });
    }

    /**
     * Ham token ile bileti bulur. Geçersiz, süresi dolmuş ya da kullanılmışsa
     * null döner -- çağıran 410 gösterir.
     */
    public function tokenlaBul(string $token): ?BasvuruBileti
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $bilet = BasvuruBileti::with('basvuru.evraklar.turu', 'basvuru.kurum')
            ->where('token_hash', BasvuruBileti::tokenHash($token))
            ->first();

        return $bilet?->kullanilabilirMi() ? $bilet : null;
    }

    private function gecerlilikGun(): int
    {
        $gun = (int) Ayar::al('duzeltme_bileti_gun', 14);

        return $gun > 0 ? $gun : throw new RuntimeException('Düzeltme bağlantısı süresi en az 1 gün olmalı.');
    }
}
