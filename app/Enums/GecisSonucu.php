<?php

namespace App\Enums;

/** Turnike/gise okutma sonucu -- her okutma sonucu ne olursa olsun loglanir. */
enum GecisSonucu: string
{
    case Izinli = 'izinli';
    case Askida = 'askida';
    case Iptal = 'iptal';
    case Bulunamadi = 'bulunamadi';
    case ImzaGecersiz = 'imza_gecersiz';
    case BolgeYetkisiYok = 'bolge_yetkisi_yok';
    case MukerrerOkutma = 'mukerrer_okutma';
    case BaskaKapida = 'baska_kapida';

    public function etiket(): string
    {
        return match ($this) {
            self::Izinli => 'İzinli',
            self::Askida => 'Askıda',
            self::Iptal => 'İptal edilmiş',
            self::Bulunamadi => 'Kayıt bulunamadı',
            self::ImzaGecersiz => 'İmza geçersiz',
            self::BolgeYetkisiYok => 'Bölge yetkisi yok',
            self::MukerrerOkutma => 'Az önce okutuldu',
            self::BaskaKapida => 'Başka kapıda okutuldu',
        };
    }

    /**
     * Geçişi ENGELLEMEYEN, yalnızca görevliyi uyaran sonuçlar.
     *
     * 💀 Kodun yorumu "geçişi engellemez" diyordu ama `basarili()` yalnızca
     * `Izinli`'ye evet dediği için görevli KIRMIZI RET EKRANI ve ret titreşimi
     * görüyordu (Düzeltme listesi md.12). Turnikeden geçen biri 30 saniye
     * içinde ikinci kez okuttuğunda -- kamera yakaladı, görevli tekrar
     * denedi, kişi geri döndü -- kırmızı ekran çıkıyordu. Maç günü
     * kalabalığında bu, görevlinin sisteme güvenini bitirir.
     */
    public function uyariMi(): bool
    {
        return in_array($this, [self::MukerrerOkutma, self::BaskaKapida], true);
    }

    public function basarili(): bool
    {
        return $this === self::Izinli || $this->uyariMi();
    }

    /**
     * Rozet rengi. TEK TANIM: bu ifade üye panosundaki "son geçişlerim"
     * kutusunda satır içine yazılmıştı; yönetim tarafında dört ekran daha
     * aynısına ihtiyaç duyunca kopyalanacaktı. Biri düzeltilip diğerleri
     * unutulmasın diye enum'a alındı.
     *
     * ♿ Renk tek başına bilgi taşımaz: her kullanıldığı yerde etiket() metni
     * de basılıyor.
     */
    public function renk(): string
    {
        return match (true) {
            $this->uyariMi() => 'warning',
            $this->basarili() => 'success',
            default => 'danger',
        };
    }
}
