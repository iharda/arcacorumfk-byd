<?php

namespace App\Enums;

/**
 * Bir düzeltme turunun NE OLDUĞU -- Cüneyt Bey revizyonu (05.09.2026).
 *
 * 💀 Tek tür vardı ve `BasvuruAkisi::eksikEvrakIste()` iki işi birden
 * yapıyordu: belge istemek VE başvuruyu karar öncesine (`eksik_evrak`)
 * döndürmek. Akredite bir kişiden tek bir belge istemek isteyen yetkili
 * bu yüzden önce "Akreditasyonu geri al" demek zorunda kalıyordu; o adım
 * kartı GERİ ALINAMAZ biçimde iptal ediyor, rolü düşürüyor ve bütün onay
 * turunu baştan çalıştırıyordu. Bir fotoğraf için kart yakılıyordu.
 *
 * İki tur ayrıldı:
 *   duzeltme     -- KARAR ÖNCESİ. Başvuru `eksik_evrak`a düşer, cevap
 *                   gelince yeniden incelemeye girer. Eski davranış.
 *   belge_talebi -- KARAR SONRASI. Başvurunun DURUMU DEĞİŞMEZ, kart aktif
 *                   kalır, turnike erişimi kesilmez. Yalnızca belge istenir.
 */
enum DuzeltmeTuru: string
{
    case Duzeltme = 'duzeltme';

    case BelgeTalebi = 'belge_talebi';

    public function etiket(): string
    {
        return match ($this) {
            self::Duzeltme => 'Düzeltme talebi',
            self::BelgeTalebi => 'Belge talebi',
        };
    }

    /** Başvurunun durumunu değiştirir mi? */
    public function durumuDegistirirMi(): bool
    {
        return $this === self::Duzeltme;
    }
}
