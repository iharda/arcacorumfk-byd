<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\KapiIstemcileri\KapiIstemcisiResource;
use App\Models\Ayar;
use App\Models\GecisKaydi;
use App\Models\KapiIstemcisi;

/**
 * Kapı istemcisi detayı -- S1.
 *
 * Cihaz künyesi, anahtar öneki, IP kısıtı, son görülme ve O KAPIDAN geçen son
 * okutmalar. Kapı ekranları videoda sonraya bırakılmıştı; detay sayfası
 * geldiği için burası da kullanılabilir hâle geliyor.
 *
 * 🔒 Anahtarın KENDİSİ gösterilmez, yalnızca öneki: hash'i saklıyoruz, ham
 * anahtar yalnızca üretim anında bir kez görünür.
 */
class KapiIstemcisiDetay extends DetaySayfasi
{
    protected static string $resource = KapiIstemcisiResource::class;

    protected static ?string $title = 'Kapı istemcisi';

    public function kimlik(): string
    {
        return $this->kayit()->ad;
    }

    public function altBaslik(): ?string
    {
        return $this->kayit()->kapi_kodu;
    }

    public function durumRozeti(): ?array
    {
        return $this->kayit()->aktif
            ? ['etiket' => 'Etkin', 'renk' => 'success']
            : ['etiket' => 'Kapalı', 'renk' => 'gray'];
    }

    public function kunye(): array
    {
        $k = $this->kayit();
        $bolgeler = (array) Ayar::al('bolgeler', []);

        return [
            'Kapı kodu' => ['deger' => $k->kapi_kodu, 'kopyala' => true],
            'Anahtar öneki' => ['deger' => $k->anahtar_onek, 'kopyala' => true],
            'IP kısıtı' => $k->ip_listesi ? implode(', ', $k->ip_listesi) : 'Uygulanmıyor',
            'Açtığı bölgeler' => $k->bolgeler
                ? implode(', ', array_map(fn ($b) => $bolgeler[$b] ?? $b, $k->bolgeler))
                : 'Bölge kontrolü yok',
            'Son görülme' => $k->son_kullanim_at?->timezone('Europe/Istanbul')?->format('d.m.Y H:i'),
            'Son görülen IP' => $k->son_kullanim_ip,
        ];
    }

    public function sekmeler(): array
    {
        $okutmalar = GecisKaydi::query()
            ->with('akreditasyon.kullanici')
            ->where('kapi_istemcisi_id', $this->kayit()->id)
            ->latest('okundu_at')
            ->limit(20)
            ->get();

        return [
            'okutmalar' => [
                'baslik' => 'Son okutmalar',
                'rozet' => $okutmalar->count() ?: null,
                'view' => 'filament.yonetim.kapi.okutmalar',
                'veri' => ['okutmalar' => $okutmalar],
            ],
        ];
    }

    private function kayit(): KapiIstemcisi
    {
        return $this->getRecord();
    }
}
