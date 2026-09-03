<?php

namespace App\Enums;

/**
 * Yetkili değerlendirme ölçeği -- Geliştirme briefi 28.08.2026 md.1.
 * Tek eksen: 1 = çok olumsuz (kırmızı) … 5 = çok olumlu (yeşil).
 *
 * 🔑 Puan KARAR DEĞİLDİR: onay/red akışını hiçbir yerde etkilemez, kimseyi
 * engellemez. Yetkiliye geçmişi hatırlatan bir işarettir.
 */
enum DegerlendirmePuani: int
{
    case CokOlumsuz = 1;
    case Olumsuz = 2;
    case Notr = 3;
    case Olumlu = 4;
    case CokOlumlu = 5;

    public function etiket(): string
    {
        return match ($this) {
            self::CokOlumsuz => 'Çok olumsuz',
            self::Olumsuz => 'Olumsuz',
            self::Notr => 'Nötr',
            self::Olumlu => 'Olumlu',
            self::CokOlumlu => 'Çok olumlu',
        };
    }

    /**
     * Filament rozet/düğme rengi.
     *
     * 🪤 Filament renk adları ÜÇ kademe veriyor (danger/warning/success);
     * istenen beş kademeli kırmızı→yeşil geçişini vermiyor. Rozette ve
     * düğmede bu, şeritte hex() kullanılır. `FilamentColor::register()` ile
     * beş özel renk tanımlamak üç panel provider'ına da dokunmayı
     * gerektirdiği için TERCİH EDİLMEDİ.
     */
    public function renk(): string
    {
        return match ($this) {
            self::CokOlumsuz, self::Olumsuz => 'danger',
            self::Notr => 'warning',
            self::Olumlu, self::CokOlumlu => 'success',
        };
    }

    /** Şeridin gerçek rengi -- satır içi stille basılır. */
    public function hex(): string
    {
        return match ($this) {
            self::CokOlumsuz => '#b91c1c',
            self::Olumsuz => '#ef4444',
            self::Notr => '#eab308',
            self::Olumlu => '#84cc16',
            self::CokOlumlu => '#16a34a',
        };
    }

    /** @return array<int, string> 1 => '1 · Çok olumsuz' */
    public static function secenekler(): array
    {
        $secenekler = [];

        foreach (self::cases() as $puan) {
            $secenekler[$puan->value] = $puan->value.' · '.$puan->etiket();
        }

        return $secenekler;
    }

    /** @return array<int, string> ToggleButtons renkleri */
    public static function renkler(): array
    {
        $renkler = [];

        foreach (self::cases() as $puan) {
            $renkler[$puan->value] = $puan->renk();
        }

        return $renkler;
    }

    /** Dikkat gerektiren puanlar -- yönetim panosu uyarı listesi. */
    public static function dusuk(): array
    {
        return [self::CokOlumsuz->value, self::Olumsuz->value];
    }
}
