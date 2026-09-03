<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Pages;

use App\Filament\Yonetim\Resources\Kullanicilar\KullaniciResource;
use App\Filament\Yonetim\Resources\Kullanicilar\Schemas\KullaniciFormu;
use App\Models\User;
use App\Servisler\DenetimYazici;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

/**
 * Kullanıcı künyesi düzenleme -- T13.
 *
 * ⚠️ E-posta bu formda YOK: giriş kimliğini değiştirmek ayrı ve daha ağır bir
 * karar, ayrı eylemi var. Buradaki alanlar serbestçe düzeltilebilir.
 */
class KullaniciDuzenle extends EditRecord
{
    protected static string $resource = KullaniciResource::class;

    protected static ?string $title = 'Kullanıcı künyesi';

    public function form(Schema $schema): Schema
    {
        return $schema->components(KullaniciFormu::alanlar());
    }

    protected function getRedirectUrl(): string
    {
        return KullaniciResource::getUrl('detay', ['record' => $this->getRecord()]);
    }

    /** @var array<string, mixed> */
    private array $oncekiDeger = [];

    protected function beforeSave(): void
    {
        /** @var User $kullanici */
        $kullanici = $this->getRecord();

        $this->oncekiDeger = collect($kullanici->getDirty())
            ->keys()
            ->mapWithKeys(fn (string $alan) => [$alan => $kullanici->getOriginal($alan)])
            ->all();
    }

    protected function afterSave(): void
    {
        if ($this->oncekiDeger === []) {
            return;
        }

        $kullanici = $this->getRecord();

        app(DenetimYazici::class)->yaz('kullanici.duzenlendi', $kullanici,
            eski: $this->oncekiDeger,
            yeni: collect($this->oncekiDeger)->keys()
                ->mapWithKeys(fn (string $alan) => [$alan => $kullanici->{$alan}])
                ->all());
    }
}
