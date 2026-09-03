<?php

namespace App\Filament\Yonetim\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;

/**
 * Yonetim paneli panosu -- Geliştirme briefi 28.08.2026, Bölüm B.
 *
 * Kulüp yetkilisinin güne başladığı ekran: kuyruk yaşı, karar dağılımı,\n * maç günü geçiş akışı ve elini değdirmesi gereken satırlar.
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
