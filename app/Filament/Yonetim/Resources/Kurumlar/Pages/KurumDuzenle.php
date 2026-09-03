<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Pages;

use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use App\Filament\Yonetim\Resources\Kurumlar\Schemas\KurumFormu;
use App\Models\Kurum;
use App\Servisler\DenetimYazici;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

/**
 * Kurum künyesi düzenleme -- T5 + E6.
 *
 * 🔒 Yetki: KurumPolicy::update() -> `kurum.yonet`. Filament EditRecord bunu
 * kendi kontrol ediyor; ekran açılmadan önce policy'den geçiyor.
 *
 * 📝 Değişiklik DENETİME yazılır. Kontenjan başvuru kabulünü doğrudan
 * etkilediği için "kim ne zaman değiştirdi" sorusunun cevabı olmalı.
 */
class KurumDuzenle extends EditRecord
{
    protected static string $resource = KurumResource::class;

    protected static ?string $title = 'Kurum künyesi';

    public function form(Schema $schema): Schema
    {
        return $schema->components(KurumFormu::alanlar());
    }

    protected function getRedirectUrl(): string
    {
        return KurumResource::getUrl('detay', ['record' => $this->getRecord()]);
    }

    /**
     * 🪤 Eski değerler kaydetmeden ÖNCE alınmalı: afterSave'de model artık
     * yeni değerleri taşıyor ve denetim kaydı "eski = yeni" diye yazardı.
     *
     * @var array<string, mixed>
     */
    private array $oncekiDeger = [];

    protected function beforeSave(): void
    {
        /** @var Kurum $kurum */
        $kurum = $this->getRecord();

        $this->oncekiDeger = collect($kurum->getDirty())
            ->keys()
            ->mapWithKeys(fn (string $alan) => [$alan => $kurum->getOriginal($alan)])
            ->all();
    }

    protected function afterSave(): void
    {
        if ($this->oncekiDeger === []) {
            return;   // gerçek bir değişiklik yoksa denetimde gürültü olmasın
        }

        $kurum = $this->getRecord();

        app(DenetimYazici::class)->yaz('kurum.duzenlendi', $kurum,
            eski: $this->oncekiDeger,
            yeni: collect($this->oncekiDeger)->keys()
                ->mapWithKeys(fn (string $alan) => [$alan => $kurum->{$alan}])
                ->all());
    }
}
