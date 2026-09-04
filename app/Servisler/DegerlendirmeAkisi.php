<?php

namespace App\Servisler;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\Kurum;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Değerlendirme yazma/okuma -- TEK kapı (BasvuruAkisi / AkreditasyonAkisi
 * kalıbı). Ekranlar doğrudan `Degerlendirme::updateOrCreate()` çağırmaz.
 *
 * Bir yerde toplanmasının sebebi e-posta normalizasyonu: yazma ve okuma aynı
 * fonksiyondan geçmezse "Ali@x.com" ve "ali@x.com" İKİ AYRI puan üretir ve
 * kısmi benzersiz indeks bunu yakalayamaz (farklı iki değerdir).
 */
class DegerlendirmeAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    /** Kişi hedefinin anahtarı -- yazma ve okuma AYNI yerden geçsin. */
    public static function epostaAnahtari(?string $eposta): ?string
    {
        return filled($eposta) ? Str::lower(trim($eposta)) : null;
    }

    /**
     * Kişi değerlendirmesini yeni e-posta adresine taşır -- M9 №9.
     *
     * 💀 `degerlendirmeler.eposta` kişi hedefinin KALICI anahtarı; puan hesap
     * açılmadan önce de verilebiliyor. Ama yetkili "E-posta değiştir" dediğinde
     * yalnız `users.email` taşınıyordu: değerlendirme ESKİ adreste kalıyor,
     * kişinin puan ve not geçmişi kopuyordu. Ekranda "değerlendirilmemiş"
     * görünüyor, yetkili aynı kişiyi ikinci kez puanlıyordu.
     *
     * ⚠️ Yeni adreste ZATEN bir değerlendirme varsa taşınmaz: iki geçmişi
     * birleştirmek hangi notun kalacağına karar vermek demektir ve o kararı
     * sessizce vermeyiz. Böyle bir durumda ikisi de yerinde kalır.
     */
    public function epostayiTasi(string $eski, string $yeni): ?Degerlendirme
    {
        $eskiAnahtar = self::epostaAnahtari($eski);
        $yeniAnahtar = self::epostaAnahtari($yeni);

        if ($eskiAnahtar === null || $yeniAnahtar === null || $eskiAnahtar === $yeniAnahtar) {
            return null;
        }

        $kayit = $this->kisiIcin($eskiAnahtar);

        if ($kayit === null || $this->kisiIcin($yeniAnahtar) !== null) {
            return null;
        }

        $kayit->update(['eposta' => $yeniAnahtar]);

        $this->denetim->yaz('degerlendirme.eposta_tasindi', $kayit,
            eski: ['eposta' => $eskiAnahtar],
            yeni: ['eposta' => $yeniAnahtar]);

        return $kayit;
    }

    public function kurumIcin(?Kurum $kurum): ?Degerlendirme
    {
        if ($kurum === null) {
            return null;
        }

        return Degerlendirme::query()
            ->where('hedef_tip', Degerlendirme::HEDEF_KURUM)
            ->where('kurum_id', $kurum->getKey())
            ->first();
    }

    /** E-posta ile; hesap olsun olmasın çalışır. */
    public function kisiIcin(?string $eposta): ?Degerlendirme
    {
        $anahtar = self::epostaAnahtari($eposta);

        if ($anahtar === null) {
            return null;
        }

        return Degerlendirme::query()
            ->where('hedef_tip', Degerlendirme::HEDEF_KISI)
            ->where('eposta', $anahtar)
            ->first();
    }

    /**
     * Başvuru ekranı için kısa yol: türüne göre doğru hedefi getirir.
     * Kurumsal başvuruda kurum, bireysel başvuruda başvuranın kendisi.
     */
    public function basvuruIcin(Basvuru $basvuru): ?Degerlendirme
    {
        return $basvuru->tur === BasvuruTuru::Kurum
            ? $this->kurumIcin($basvuru->kurum)
            : $this->kisiIcin($basvuru->basvuranEpostasi());
    }

    public function kurumaYaz(Kurum $kurum, int $puan, ?string $not): Degerlendirme
    {
        $this->puaniDogrula($puan);

        $mevcut = $this->kurumIcin($kurum);

        $kayit = Degerlendirme::updateOrCreate(
            ['hedef_tip' => Degerlendirme::HEDEF_KURUM, 'kurum_id' => $kurum->getKey()],
            $this->ortakAlanlar($puan, $not) + ['hedef_ad' => $kurum->resmi_unvan],
        );

        $this->denetimeYaz($mevcut, $kayit, $kurum, $kurum->resmi_unvan);

        return $kayit;
    }

    public function kisiyeYaz(string $eposta, ?string $ad, int $puan, ?string $not): Degerlendirme
    {
        $this->puaniDogrula($puan);

        $anahtar = self::epostaAnahtari($eposta)
            ?? throw new RuntimeException('Değerlendirme için e-posta adresi gerekli.');

        $mevcut = $this->kisiIcin($anahtar);

        $kayit = Degerlendirme::updateOrCreate(
            ['hedef_tip' => Degerlendirme::HEDEF_KISI, 'eposta' => $anahtar],
            $this->ortakAlanlar($puan, $not) + [
                'hedef_ad' => $ad ?? $mevcut?->hedef_ad,
                // Hesap zaten varsa hemen bağla; yoksa hesabaBagla() halleder.
                'kullanici_id' => $mevcut->kullanici_id
                    ?? User::withTrashed()->where('email', $anahtar)->value('id'),
            ],
        );

        $this->denetimeYaz($mevcut, $kayit, $kayit->kullanici, trim(($ad ?? $kayit->hedef_ad ?? '').' <'.$anahtar.'>'));

        return $kayit;
    }

    /**
     * Hesap açıldığında e-posta anahtarlı puanı hesaba bağlar.
     *
     * 🔑 `HesapAcici::onaydanOlustur()` içinden, AYNI transaction'da çağrılır:
     * puan hesap doğmadan önce verilmiş olabilir ve o satırda `kullanici_id`
     * boştur. Bağ kurulmazsa kullanıcılar tablosundaki sütun boş görünür.
     */
    public function hesabaBagla(User $kullanici): void
    {
        $anahtar = self::epostaAnahtari($kullanici->email);

        if ($anahtar === null) {
            return;
        }

        Degerlendirme::query()
            ->where('hedef_tip', Degerlendirme::HEDEF_KISI)
            ->where('eposta', $anahtar)
            ->whereNull('kullanici_id')
            ->update(['kullanici_id' => $kullanici->getKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ortakAlanlar(int $puan, ?string $not): array
    {
        $aktor = Auth::user();

        return [
            'puan' => $puan,
            'not' => filled($not) ? $not : null,
            'degerlendiren_id' => $aktor?->getKey(),
            // Aktör silinse de kimin puanladığı ekranda kalsın.
            'degerlendiren_ad' => $aktor?->name,
        ];
    }

    /**
     * 🔒 Ölçek doğrulaması SERVİSTE de yapılır. Veritabanı kısıtı son savunma
     * hattıdır, ilk değil: 500 yerine anlaşılır bir hata çıksın.
     */
    private function puaniDogrula(int $puan): void
    {
        if ($puan < 1 || $puan > 5) {
            throw new RuntimeException("Değerlendirme puanı 1-5 aralığında olmalı; {$puan} verildi.");
        }
    }

    /**
     * 🪤 Kişi hedefinin HESABI OLMAYABİLİR; o zaman denetim satırı hiçbir
     * kayda bağlanamaz. Hedefin adı/e-postası `not` alanına yazılır, yoksa
     * denetim ekranında "kime verilmiş" sorusu cevapsız kalır.
     */
    private function denetimeYaz(?Degerlendirme $eski, Degerlendirme $yeni, ?Model $hedefModel, string $hedefAdi): void
    {
        $this->denetim->yaz(
            $eski === null ? 'degerlendirme.verildi' : 'degerlendirme.guncellendi',
            $hedefModel,
            eski: $eski === null ? null : ['puan' => $eski->puan->value, 'not' => $eski->not],
            yeni: ['puan' => $yeni->puan->value, 'not' => $yeni->not],
            not: 'Hedef: '.$hedefAdi,
        );
    }
}
