<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Kullanicilar\KullaniciResource;
use App\Models\User;
use App\Support\Telefon;
use Filament\Actions\Action;

/**
 * Kullanıcı detayı -- T13. Ortak şablonun (S1) üçüncü uygulaması.
 *
 * Videodaki cümle: "Bu kullanıcıların içine girip düzenleme yapmalıyım
 * arkadaşlar -- düzenleme yapma şansım yok. Yarın bir gün bir şey olduğunda
 * benim bunu düzenliyor olabilmem lazım."
 *
 * 🔒 UserPolicy::update() `kullanici.yonet` istiyor ve yetkili rolünde bu
 * yetki VAR; yine yalnızca ekran eksikti.
 */
class KullaniciDetay extends DetaySayfasi
{
    protected static string $resource = KullaniciResource::class;

    protected static ?string $title = 'Kullanıcı';

    public function kimlik(): string
    {
        return $this->kayit()->name;
    }

    public function altBaslik(): ?string
    {
        return $this->kayit()->email;
    }

    public function durumRozeti(): ?array
    {
        return $this->kayit()->aktif
            ? ['etiket' => 'Aktif', 'renk' => 'success']
            : ['etiket' => 'Pasif', 'renk' => 'gray'];
    }

    public function kunye(): array
    {
        $k = $this->kayit();

        return [
            'E-posta' => ['deger' => $k->email, 'kopyala' => true],
            'Telefon' => $k->telefon ? Telefon::goster($k->telefon) : null,
            'Kurum' => $k->kurum?->resmi_unvan,
            'Roller' => $k->getRoleNames()->implode(', ') ?: null,
            'Adres' => $k->adres,
            'İl / ilçe' => collect([$k->il, $k->ilce])->filter()->implode(' / ') ?: null,
            'İki adımlı' => $k->iki_adimli_gizli ? 'Kurulu' : 'Kurulu değil',
            'Son giriş' => $k->son_giris_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i'),
        ];
    }

    public function sekmeler(): array
    {
        $k = $this->kayit();

        $akreditasyonlar = $k->akreditasyonlar()->with('kurum')->latest('id')->get();
        $basvurular = $k->basvurular()->latest('id')->limit(20)->get();

        return [
            'akreditasyonlar' => [
                'baslik' => 'Akreditasyonları',
                'rozet' => $akreditasyonlar->count() ?: null,
                'view' => 'filament.yonetim.kullanici.akreditasyonlar',
                'veri' => ['akreditasyonlar' => $akreditasyonlar],
            ],
            'basvurular' => [
                'baslik' => 'Başvuruları',
                'rozet' => $basvurular->count() ?: null,
                'view' => 'filament.yonetim.kullanici.basvurular',
                'veri' => ['basvurular' => $basvurular],
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('duzenle')
                ->label('Künyeyi düzenle')
                ->icon('heroicon-m-pencil-square')
                ->visible(fn () => auth()->user()?->can('update', $this->kayit()) ?? false)
                ->url(fn () => KullaniciResource::getUrl('duzenle', ['record' => $this->kayit()])),
        ];
    }

    private function kayit(): User
    {
        return $this->getRecord();
    }
}
