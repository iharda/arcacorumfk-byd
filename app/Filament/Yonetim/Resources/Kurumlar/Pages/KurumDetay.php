<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use App\Models\Kurum;
use App\Support\Telefon;
use Filament\Actions\Action;

/**
 * Kurum detayı -- T5. Ortak şablonun (S1) ikinci uygulaması.
 *
 * Videodaki cümle: "Kurumlar sadece okuma ekranı, ben burada hiçbir işlem
 * yapamıyorum." Künye buraya, düzenleme ayrı sayfaya (KurumDuzenle) taşındı.
 */
class KurumDetay extends DetaySayfasi
{
    protected static string $resource = KurumResource::class;

    protected static ?string $title = 'Kurum';

    private const DURUMLAR = [
        'beklemede' => ['Beklemede', 'warning'],
        'akredite' => ['Akredite', 'success'],
        'iptal' => ['İptal', 'danger'],
    ];

    public function kimlik(): string
    {
        return $this->kayit()->resmi_unvan;
    }

    public function altBaslik(): ?string
    {
        $k = $this->kayit();

        return collect([$k->il, $k->ilce])->filter()->implode(' / ') ?: null;
    }

    public function durumRozeti(): ?array
    {
        [$etiket, $renk] = self::DURUMLAR[$this->kayit()->akreditasyon_durumu]
            ?? [$this->kayit()->akreditasyon_durumu, 'gray'];

        return ['etiket' => $etiket, 'renk' => $renk];
    }

    public function kunye(): array
    {
        $k = $this->kayit();

        return [
            'Vergi / T.C. no' => ['deger' => $k->vergi_no, 'kopyala' => true],
            'Vergi dairesi' => $k->vergi_dairesi,
            'E-posta' => ['deger' => $k->eposta, 'kopyala' => true],
            'Telefon' => $k->telefon ? Telefon::goster($k->telefon) : null,
            'Adres' => $k->adres,
            'İl / ilçe' => collect([$k->il, $k->ilce])->filter()->implode(' / ') ?: null,
            'Çalışan sayısı' => $k->calisan_araligi?->etiket(),
            'Kontenjan' => $this->kontenjanMetni(),
        ];
    }

    public function sekmeler(): array
    {
        $k = $this->kayit();

        $calisanlar = $k->calisanlar()->orderBy('name')->get();
        $akreditasyonlar = $k->akreditasyonlar()->with('kullanici')->latest('id')->get();
        $basvurular = $k->basvurular()->latest('id')->limit(20)->get();

        return [
            'calisanlar' => [
                'baslik' => 'Çalışanlar',
                'rozet' => $calisanlar->count() ?: null,
                'view' => 'filament.yonetim.kurum.calisanlar',
                'veri' => ['calisanlar' => $calisanlar],
            ],
            'akreditasyonlar' => [
                'baslik' => 'Akreditasyonlar',
                'rozet' => $akreditasyonlar->count() ?: null,
                'view' => 'filament.yonetim.kurum.akreditasyonlar',
                'veri' => ['akreditasyonlar' => $akreditasyonlar],
            ],
            'basvurular' => [
                'baslik' => 'Başvuru geçmişi',
                'rozet' => $basvurular->count() ?: null,
                'view' => 'filament.yonetim.kurum.basvurular',
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
                ->url(fn () => KurumResource::getUrl('duzenle', ['record' => $this->kayit()])),
        ];
    }

    /** "3 / 10" ya da "3 · sınırsız" -- kontenjanDoldu() kuralının okunur hâli. */
    private function kontenjanMetni(): string
    {
        $k = $this->kayit();
        $aktif = $k->akreditasyonlar()->where('durum', 'aktif')->count();

        return $k->kontenjan === null
            ? $aktif.' aktif · sınırsız'
            : $aktif.' / '.$k->kontenjan.($k->kontenjanDoldu() ? ' · DOLU' : '');
    }

    private function kayit(): Kurum
    {
        return $this->getRecord();
    }
}
