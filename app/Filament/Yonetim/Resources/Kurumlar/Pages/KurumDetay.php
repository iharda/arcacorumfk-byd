<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Pages;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
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

    /** ⚠️ `iptal` ile `iptal_edildi` ayrımı için bkz. KurumlarTable::DURUMLAR (M1-A). */
    private const DURUMLAR = [
        'beklemede' => ['Beklemede', 'warning'],
        'akredite' => ['Akredite', 'success'],
        'iptal' => ['İptal', 'danger'],
        'reddedildi' => ['Reddedildi', 'danger'],
        'iptal_edildi' => ['Başvuru iptal edildi', 'gray'],
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
            /*
             * M2.4 md.4: bu ikisi başvuru formunda toplanıp yalnızca inceleme
             * ekranında görünüyordu. Kurum onaylandıktan sonra "bu kuruluş
             * nerede yayın yapıyor" sorusunun cevabı hiçbir ekranda yoktu.
             */
            'Yayın platformları' => $this->listeMetni($k->yayin_platformlari),
            'Sosyal medya' => $this->listeMetni($k->sosyal_medya),
        ];
    }

    public function sekmeler(): array
    {
        $k = $this->kayit();

        $calisanlar = $k->calisanlar()->orderBy('name')->get();
        $akreditasyonlar = $k->akreditasyonlar()->with('kullanici')->latest('id')->get();
        $basvurular = $k->basvurular()->latest('id')->limit(20)->get();

        /*
         * Evrak sekmesinin kaynağı: kurumun EN SON ONAYLANMIŞ kurumsal
         * başvurusu. Onaylanmış olan seçiliyor çünkü kurumun bugünkü
         * akreditasyonu ona dayanıyor; sonradan gönderilmiş yarım bir başvuru
         * geçerli evrakın önüne geçmemeli. Hiç onaylanmışı yoksa en son
         * kurumsal başvuruya düşülür -- inceleme sürerken de evrak görünsün.
         */
        $evrakBasvurusu = $k->basvurular()
            ->where('tur', BasvuruTuru::Kurum->value)
            ->orderByRaw('CASE WHEN durum = ? THEN 0 ELSE 1 END', [BasvuruDurumu::Onaylandi->value])
            ->latest('id')
            ->first();

        $evraklar = $evrakBasvurusu?->evraklar()->with('turu')->get() ?? collect();

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

            /*
             * 💀 M2: onaylanmış bir kurumun Ticaret Sicili Gazetesi'ne ulaşmanın
             * tek yolu Kurumlar → detay → Başvuru geçmişi → numaraya tıkla →
             * inceleme ekranı idi. Üç tıklama ve ekran değiştirme.
             *
             * 🔑 Kurumsal onayda AKREDİTASYON KAYDI DOĞMUYOR
             * (AkreditasyonAkisi:33 `return null`), yani kurumun evrakları için
             * "Akreditasyon detayı" gibi bir ev de yok. O ev burası.
             */
            'evraklar' => [
                'baslik' => 'Evraklar',
                'rozet' => $evraklar->count() ?: null,
                'view' => 'filament.yonetim.kurum.evraklar',
                'veri' => ['evraklar' => $evraklar, 'basvuru' => $evrakBasvurusu],
            ],

            /*
             * 🔒 Yalnızca `degerlendirme.yonet`. Puan ve not kulüp dışına
             * çıkmaz; kurum kendi panelinde bunu göremez.
             */
            ...(auth()->user()?->can('degerlendirme.yonet') ? ['degerlendirme' => [
                'baslik' => 'Değerlendirme',
                'view' => 'filament.yonetim.kurum.degerlendirme',
                'veri' => ['degerlendirme' => $k->degerlendirme, 'kurumAdi' => $k->resmi_unvan],
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
                ->url(fn () => KurumResource::getUrl('duzenle', ['record' => $this->kayit()])),

            // Puanlama Kurumlar TABLOSUNDA vardı, detayda yoktu: yetkili kurumu
            // inceledikten sonra listeye dönmek zorunda kalıyordu (M2.4 md.2).
            DegerlendirmeEylemi::kurumSayfasi(fn () => $this->kayit()),
        ];
    }

    /**
     * Künye tek satır metin basar (şablon dizi bilmez), bu yüzden iki farklı
     * şekli de düz metne indiriyoruz:
     *   yayin_platformlari -> [['ad' => .., 'url' => ..], ..]
     *   sosyal_medya       -> ['twitter' => url, ..]
     * Boş değerler ayıklanır ki künyede "—" yerine yarım liste çıkmasın.
     */
    private function listeMetni(?array $deger): ?string
    {
        $satirlar = collect($deger ?? [])
            ->map(fn ($v, $k) => is_array($v)
                ? trim(($v['ad'] ?? '').' ('.($v['url'] ?? '').')', ' ()')
                : (filled($v) ? "{$k}: {$v}" : null))
            ->filter()
            ->values();

        return $satirlar->isEmpty() ? null : $satirlar->implode(' · ');
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
