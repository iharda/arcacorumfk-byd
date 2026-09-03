<?php

namespace App\Filament\Yonetim\Resources\Akreditasyonlar\Pages;

use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Akreditasyonlar\AkreditasyonResource;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Servisler\DenetimYazici;
use App\Support\Telefon;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Akreditasyon detayı -- T11, ortak şablonun (S1) ilk uygulaması.
 *
 * 🔑 Bu sayfa OMURGA: kart görseli (T9), kart üretim geri bildirimi (T10) ve
 * ileride değerlendirme rozeti (T12) buraya yaslanıyor. Sayfa olmadan üçü de
 * listeye sıkıştırılmak zorundaydı.
 *
 * Kazanç: "bu kişi maç günü kaç kez okuttu, nerede takıldı" sorusu Geçiş
 * kayıtları ekranına gitmeden kapanıyor.
 */
class AkreditasyonDetay extends DetaySayfasi
{
    protected static string $resource = AkreditasyonResource::class;

    protected static ?string $title = 'Akreditasyon';

    public function kimlik(): string
    {
        return $this->kayit()->kart_no;
    }

    public function altBaslik(): ?string
    {
        $a = $this->kayit();

        return trim(($a->kullanici?->name ?? '—').' · '.($a->kurum?->resmi_unvan ?? 'Bağımsız'));
    }

    public function durumRozeti(): ?array
    {
        $durum = $this->kayit()->durum;

        return ['etiket' => $durum->etiket(), 'renk' => $durum->renk()];
    }

    public function kunye(): array
    {
        $a = $this->kayit();
        $bolgeler = (array) Ayar::al('bolgeler', []);

        return [
            'Kart no' => ['deger' => $a->kart_no, 'kopyala' => true],
            'Kişi' => $a->kullanici?->name,
            'E-posta' => ['deger' => $a->kullanici?->email, 'kopyala' => true],
            'Telefon' => $a->kullanici?->telefon ? Telefon::goster($a->kullanici->telefon) : null,
            'Kurum' => $a->kurum?->resmi_unvan ?? 'Bağımsız',
            'Sezon' => $a->sezon,
            'Geçerlilik' => $this->gecerlilik(),
            'Bölgeler' => $a->bolge_yetkileri
                ? implode(', ', array_map(fn ($b) => $bolgeler[$b] ?? $b, $a->bolge_yetkileri))
                : null,
        ];
    }

    public function sekmeler(): array
    {
        $a = $this->kayit();

        $gecisler = $a->gecisKayitlari()
            ->with('kapiIstemcisi')
            ->latest('okundu_at')
            ->limit(20)
            ->get();

        return [
            'kart' => [
                'baslik' => 'Kart',
                'view' => 'filament.yonetim.akreditasyon.kart',
                'veri' => [
                    'akreditasyon' => $a,
                    'guncel' => $a->guncelKart,
                    // Sürüm geçmişi: "yeniden üret dedim, ne oldu?" sorusunun
                    // ikinci katmanı (T10).
                    'surumler' => $a->kartlar()->with('ureten')->latest('surum')->get(),
                ],
            ],
            'gecis' => [
                'baslik' => 'Geçiş kayıtları',
                'rozet' => $gecisler->count() ?: null,
                'view' => 'filament.yonetim.akreditasyon.gecis',
                'veri' => ['gecisler' => $gecisler],
            ],
            /*
             * T12: değerlendirme detayda da blok olarak. Kişi ve kurum ayrı
             * satır; sekme yalnızca `degerlendirme.yonet` yetkisi olana çizilir
             * (kişi bu puanı hiçbir ekranda görmez).
             */
            ...(auth()->user()?->can('degerlendirme.yonet') ? ['degerlendirme' => [
                'baslik' => 'Değerlendirme',
                'view' => 'filament.yonetim.akreditasyon.degerlendirme',
                'veri' => [
                    'kisi' => $a->kullanici?->degerlendirme,
                    'kurum' => $a->kurum?->degerlendirme,
                    'kurumAdi' => $a->kurum?->resmi_unvan,
                ],
            ]] : []),

            'basvuru' => [
                'baslik' => 'Başvuru ve evraklar',
                'view' => 'filament.yonetim.akreditasyon.basvuru',
                'veri' => [
                    'basvuru' => $a->basvuru,
                    'evraklar' => $a->basvuru?->evraklar()->with('turu')->get() ?? collect(),
                ],
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kartPdf')
                ->label('Kart PDF indir')
                ->icon('heroicon-m-arrow-down-tray')
                /*
                 * 🔒 `kart.indir` yetkisi yönetimde de sorulur. Üye panelinde
                 * zaten kontrol ediliyordu; yönetim tarafı eklenirken kuralın
                 * yarısını uygulamak, yetkiyi hiç sormamakla aynı kapıya çıkar.
                 */
                ->visible(fn () => $this->kayit()->guncelKart?->pdf_yolu !== null
                    && Auth::user()?->can('kart.indir'))
                ->action(fn (): StreamedResponse => $this->pdfAkisi()),
        ];
    }

    private function pdfAkisi(): StreamedResponse
    {
        // Görünürlük yetmez: eylem adresi doğrudan da çağrılabilir.
        abort_unless(Auth::user()?->can('kart.indir'), 403);

        $a = $this->kayit();
        $kart = $a->guncelKart;

        // S5: kart PDF'i HER indirmede denetime yazılır -- yönetim tarafında da.
        app(DenetimYazici::class)->yaz('kart.indirildi', $a, yeni: [
            'kart_no' => $a->kart_no,
            'surum' => $kart->surum,
        ]);

        return Storage::disk($kart->disk)->download(
            $kart->pdf_yolu,
            'basin-karti-'.$a->kart_no.'.pdf',
        );
    }

    private function gecerlilik(): ?string
    {
        $a = $this->kayit();

        if (! $a->gecerlilik_baslangic && ! $a->gecerlilik_bitis) {
            return null;
        }

        $bicim = fn (?object $t) => $t?->timezone('Europe/Istanbul')->format('d.m.Y') ?? '—';

        return $bicim($a->gecerlilik_baslangic).' – '.$bicim($a->gecerlilik_bitis);
    }

    private function kayit(): Akreditasyon
    {
        return $this->getRecord();
    }
}
