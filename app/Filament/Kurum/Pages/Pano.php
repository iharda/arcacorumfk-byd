<?php

namespace App\Filament\Kurum\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;

/**
 * Kurum paneli panosu -- Geliştirme briefi 28.08.2026, Bölüm B.
 *
 * Kurum yetkilisi sisteme seyrek giriyor: "Benden bekleyen bir onay var mı,\n * kaç kartım var, kontenjanım doldu mu?" sorusunun cevabı tek ekranda.
 *
 * 🔤 Filament'in kendi `Dashboard` sınıfı doğrudan kaydedilseydi menüde
 * İngilizce "Dashboard" yazardı; sistemin geri kalanı Türkçe.
 */
class Pano extends Dashboard
{
    protected static ?string $navigationLabel = 'Genel bakış';

    protected static ?string $title = 'Genel bakış';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    /** @return int|array<string, ?int> */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }
}
