<?php

namespace App\Filament\Yonetim\Ortak;

use Filament\Tables\Columns\TextColumn;

/**
 * Tabloların ilk sütunu: SIRA NUMARASI.
 *
 * 🔑 Tek tanım, on bir tablo. Her tabloya elle bir sütun yazılsaydı biri
 * sıfırdan, biri birden başlar; biri sayfa 2'de yeniden 1'e dönerdi.
 *
 * 🪤 Bu numara KAYDIN KİMLİĞİ DEĞİL, satırın ekrandaki sırasıdır: süzgeç,
 * sıralama ve sayfa değişince başka bir kayda denk gelir. Bu yüzden
 * sıralanabilir/aranabilir yapılmadı ve gizlenemiyor -- "3 numaralı başvuru"
 * diye konuşulacak alan `basvuru_no`, bu değil.
 *
 * ⚠️ Sayfalama Filament'in `rowIndex()`ine bırakıldı: sayfa 2'de 1'den değil
 * kaldığı yerden devam etmesi (sayfa boyu × (sayfa-1) eklemesi) oradan gelir.
 */
class SiraSutunu
{
    public static function yap(): TextColumn
    {
        return TextColumn::make('sira_no')
            ->label('#')
            ->rowIndex()
            // Sayılar sağa dayalı ve eşit genişlikte: göz alt alta tarayabilsin.
            ->numeric()
            ->alignRight()
            ->extraCellAttributes(['style' => 'width:1%; white-space:nowrap; opacity:.55;']);
    }
}
