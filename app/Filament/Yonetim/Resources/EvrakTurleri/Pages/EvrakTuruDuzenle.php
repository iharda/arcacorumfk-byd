<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri\Pages;

use App\Filament\Yonetim\Resources\EvrakTurleri\EvrakTuruResource;
use App\Filament\Yonetim\Resources\EvrakTurleri\Schemas\EvrakTuruFormu;
use App\Models\EvrakTuru;
use App\Servisler\DenetimYazici;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

/**
 * Evrak türü düzenleme -- M7.3.
 *
 * 📝 Her değişiklik DENETİME yazılır. Bir belgeyi zorunlu yapmak ya da boyut
 * sınırını düşürmek başvuru kabulünü doğrudan etkiler; "kim ne zaman
 * değiştirdi" sorusunun cevabı olmalı.
 *
 * ⚠️ SİLME YOK (EvrakTuruResource::canDelete): mevcut evraklar bu kaydın
 * adına bakıyor. Kullanımdan kaldırma yolu `aktif = false`.
 */
class EvrakTuruDuzenle extends EditRecord
{
    protected static string $resource = EvrakTuruResource::class;

    protected static ?string $title = 'Evrak türü';

    public function form(Schema $schema): Schema
    {
        return $schema->components(EvrakTuruFormu::alanlar());
    }

    protected function getRedirectUrl(): string
    {
        return EvrakTuruResource::getUrl('index');
    }

    // Silme eylemi başlıkta da çıkmasın.
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * 🪤 Eski değerler kaydetmeden ÖNCE alınır: afterSave'de model artık yeni
     * değerleri taşır ve denetim kaydı "eski = yeni" diye yazardı.
     * (KurumDuzenle'deki aynı kalıp.)
     *
     * @var array<string, mixed>
     */
    private array $oncekiDeger = [];

    protected function beforeSave(): void
    {
        /** @var EvrakTuru $tur */
        $tur = $this->getRecord();

        $this->oncekiDeger = collect($tur->getDirty())
            ->keys()
            ->mapWithKeys(fn (string $alan) => [$alan => $tur->getOriginal($alan)])
            ->all();
    }

    protected function afterSave(): void
    {
        if ($this->oncekiDeger === []) {
            return;   // gerçek bir değişiklik yoksa denetimde gürültü olmasın
        }

        $tur = $this->getRecord();

        app(DenetimYazici::class)->yaz('evrak_turu.duzenlendi', $tur,
            eski: $this->oncekiDeger,
            yeni: collect($this->oncekiDeger)->keys()
                ->mapWithKeys(fn (string $alan) => [$alan => $tur->{$alan}])
                ->all());
    }
}
