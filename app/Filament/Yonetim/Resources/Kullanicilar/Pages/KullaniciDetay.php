<?php

namespace App\Filament\Yonetim\Resources\Kullanicilar\Pages;

use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
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

        // Evrak sekmesinin kaynağı kişinin EN SON başvurusu (M2.4 md.3).
        $evrakBasvurusu = $basvurular->first();
        $evraklar = $evrakBasvurusu?->evraklar()->with('turu')->get() ?? collect();

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

            /*
             * 💀 M2: kişinin kimlik ve çalışma belgesi yalnızca başvuru inceleme
             * ekranında görünüyordu. Onaydan sonra "bu kişi neyle akredite oldu"
             * sorusunun cevabı hiçbir kullanıcı ekranında yoktu.
             */
            'evraklar' => [
                'baslik' => 'Evraklar',
                'rozet' => $evraklar->count() ?: null,
                'view' => 'filament.yonetim.kullanici.evraklar',
                'veri' => ['evraklar' => $evraklar, 'basvuru' => $evrakBasvurusu],
            ],

            /*
             * 🔑 KİŞİ ve KURUM AYRI: kişi olumlu, çalıştığı kurum sorunlu
             * olabilir. Tek rozet yetkiliyi yanlış karara götürür.
             * 🔒 Yalnızca `degerlendirme.yonet`.
             */
            ...(auth()->user()?->can('degerlendirme.yonet') ? ['degerlendirme' => [
                'baslik' => 'Değerlendirme',
                'view' => 'filament.yonetim.kullanici.degerlendirme',
                'veri' => [
                    'kisi' => $k->degerlendirme,
                    'kurum' => $k->kurum?->degerlendirme,
                    'kurumAdi' => $k->kurum?->resmi_unvan,
                ],
            ]] : []),
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

            // Puanlama Kullanıcılar TABLOSUNDA vardı, detayda yoktu (M2.4 md.3).
            DegerlendirmeEylemi::kisiSayfasi(fn () => $this->kayit()),
        ];
    }

    private function kayit(): User
    {
        return $this->getRecord();
    }
}
