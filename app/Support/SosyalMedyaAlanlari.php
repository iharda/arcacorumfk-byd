<?php

namespace App\Support;

use App\Enums\BasvuruTuru;

/**
 * Sosyal medya / yayın kanalı kutularının TEK tanımı.
 *
 * 🔑 Aynı liste üç yerde çiziliyor: kurum formu, bireysel form ve düzeltme
 * ekranı. Üç kopya tutulduğunda düzeltme ekranı geride kalmıştı -- yetkili
 * "Yayın kanalları"nı düzeltmeye işaretlediğinde başvurana satır şekline
 * uymayan tek bir metin kutusu çıkıyor, gönderdiği form doğrulamayı hiç
 * geçemiyordu. Metinler Cüneyt Bey revizyonundan (03.09.2026).
 *
 * 🪤 Kurum ve bireysel listeler AYNI DEĞİL: kurumda Facebook var, bireyselde
 * "İnternet sitesi veya blog" var. Anahtarlar veritabanındaki `sosyal_medya`
 * JSON'unun anahtarlarıdır; değiştirmek eski kayıtları okunamaz yapar.
 */
class SosyalMedyaAlanlari
{
    /** Kurum başvurusu: kurumsal hesaplar. */
    private const KURUMSAL = [
        'x' => ['X', 'Profil bağlantısını girin'],
        'instagram' => ['Instagram', 'Profil bağlantısını girin'],
        'facebook' => ['Facebook', 'Profil bağlantısını girin'],
        'youtube' => ['YouTube', 'Profil bağlantısını girin'],
        'tiktok' => ['TikTok', 'Profil bağlantısını girin'],
    ];

    /** Basın mensubu / bağımsız içerik üreticisi: kişisel yayın kanalları. */
    private const BIREYSEL = [
        'x' => ['X profil bağlantısı', 'https://x.com/kullaniciadi'],
        'instagram' => ['Instagram profil bağlantısı', 'https://instagram.com/kullaniciadi'],
        'youtube' => ['YouTube kanal bağlantısı', 'https://youtube.com/@kanaladi'],
        'tiktok' => ['TikTok profil bağlantısı', 'https://tiktok.com/@kullaniciadi'],
        'web' => ['İnternet sitesi veya blog', 'https://ornek.com'],
    ];

    /**
     * @return array<string, array{etiket: string, ipucu: string}>
     */
    public static function turIcin(BasvuruTuru $tur): array
    {
        $ham = $tur === BasvuruTuru::Kurum ? self::KURUMSAL : self::BIREYSEL;

        $alanlar = [];

        foreach ($ham as $anahtar => [$etiket, $ipucu]) {
            $alanlar[$anahtar] = ['etiket' => $etiket, 'ipucu' => $ipucu];
        }

        return $alanlar;
    }
}
