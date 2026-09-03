<?php

namespace App\Enums;

/**
 * Basvuru durum makinesi -- Plan v1.0 md.4.
 * Gecisler gecebilirMi() ile korunur; modelde ve policy'de TEK kaynak burasi.
 *
 * 🔤 Etiketler Cuneyt Bey revizyonunda (03.09.2026) yeniden adlandirildi:
 * durum adi yetkiliye "simdi ne olmasi gerekiyor"u soylemeli. "Gonderildi"
 * bir OLAYIN adiydi, "Inceleme bekliyor" bir ISIN adi.
 *
 * 🪤 `value`'lar DEGISMEDI: veritabanindaki basvurular, denetim kaydi ve
 * CSV ciktilari bu degerlere bagli. Adlandirma yalnizca ekranda.
 */
enum BasvuruDurumu: string
{
    case Taslak = 'taslak';
    case Gonderildi = 'gonderildi';
    case Incelemede = 'incelemede';
    case EksikEvrak = 'eksik_evrak';
    /**
     * Istenen belge/bilgi geldi, basvuru tekrar kuyrukta.
     *
     * 💀 Eskiden duzeltme sonrasi `Gonderildi`'ye DONULUYORDU: kuyrukta hic
     * acilmamis basvuruyla, bir kez incelenip belge istenmis ve cevabi gelmis
     * basvuru AYNI gorunuyordu. Yetkili once hangisine bakacagini ayirt
     * edemiyordu.
     */
    case YenidenInceleme = 'yeniden_inceleme';
    case Onaylandi = 'onaylandi';
    case Reddedildi = 'reddedildi';
    /** Yetkili kuyruktaki basvuruyu dusurdu -- karar degil, vazgecis. */
    case IptalEdildi = 'iptal_edildi';

    public function etiket(): string
    {
        return match ($this) {
            self::Taslak => 'Taslak',
            self::Gonderildi => 'İnceleme bekliyor',
            self::Incelemede => 'İnceleniyor',
            self::EksikEvrak => 'Belge bekleniyor',
            self::YenidenInceleme => 'Yeniden inceleme bekliyor',
            self::Onaylandi => 'Onaylandı',
            self::Reddedildi => 'Reddedildi',
            self::IptalEdildi => 'İptal edildi',
        };
    }

    /** Durumun ne demek oldugu -- inceleme ekranindaki rozetin altinda. */
    public function aciklama(): string
    {
        return match ($this) {
            self::Taslak => 'Başvuru henüz gönderilmedi.',
            self::Gonderildi => 'Başvuru alındı ancak henüz bir yetkili tarafından incelenmeye başlanmadı.',
            self::Incelemede => 'Başvuru bir yetkili tarafından değerlendirmeye alındı.',
            self::EksikEvrak => 'Başvuru sahibinden eksik veya güncel bir belge istendi.',
            self::YenidenInceleme => 'İstenen belge veya bilgi başvuru sahibi tarafından tamamlandı.',
            self::Onaylandi => 'Başvurunun uygunluğu onaylandı.',
            self::Reddedildi => 'Başvuru uygun bulunmadı.',
            self::IptalEdildi => 'Başvuru sahibi veya yönetici başvuruyu iptal etti.',
        };
    }

    public function renk(): string
    {
        return match ($this) {
            self::Taslak => 'gray',
            self::Gonderildi => 'gray',
            self::Incelemede => 'info',
            self::EksikEvrak => 'warning',
            // "Yeni is geldi" demek: kuyrukta One cikmali.
            self::YenidenInceleme => 'info',
            self::Onaylandi => 'success',
            self::Reddedildi => 'danger',
            self::IptalEdildi => 'gray',
        };
    }

    /** @return array<int, self> */
    public function sonrakiler(): array
    {
        return match ($this) {
            self::Taslak => [self::Gonderildi],
            self::Gonderildi => [self::Incelemede, self::IptalEdildi],
            self::Incelemede => [self::EksikEvrak, self::Onaylandi, self::Reddedildi, self::IptalEdildi],
            self::EksikEvrak => [self::YenidenInceleme, self::IptalEdildi],
            self::YenidenInceleme => [self::Incelemede, self::IptalEdildi],
            self::Onaylandi, self::Reddedildi, self::IptalEdildi => [],
        };
    }

    public function gecebilirMi(self $hedef): bool
    {
        return in_array($hedef, $this->sonrakiler(), true);
    }

    /** Yetkili kuyrugunda gorunmesi gerekenler. */
    public static function kuyruk(): array
    {
        return [self::Gonderildi, self::Incelemede, self::EksikEvrak, self::YenidenInceleme];
    }

    /**
     * Yetkilinin HENUZ ACMADIGI basvurular -- panodaki "yeni basvuru" sayisi.
     *
     * @return array<int, self>
     */
    public static function acilmamis(): array
    {
        return [self::Gonderildi, self::YenidenInceleme];
    }

    /** @return array<int, string> */
    public static function degerleri(self ...$durumlar): array
    {
        return array_map(fn (self $d) => $d->value, $durumlar);
    }
}
