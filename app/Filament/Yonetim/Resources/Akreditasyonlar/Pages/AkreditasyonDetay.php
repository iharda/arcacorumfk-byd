<?php

namespace App\Filament\Yonetim\Resources\Akreditasyonlar\Pages;

use App\Filament\Yonetim\Ortak\AkreditasyonEylemleri;
use App\Filament\Yonetim\Ortak\BelgeTalebiEylemi;
use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Akreditasyonlar\AkreditasyonResource;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\BasvuruDuzeltmesi;
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

    /**
     * "Belge bekleniyor" bandı -- KurumDetay'daki bandın kişi tarafındaki eşi.
     *
     * 💀 Talep açılabilir hâle gelince ikinci bir kör nokta doğuyordu: talebin
     * varlığı yalnızca inceleme ekranında görünseydi, akreditasyona bakan
     * yetkili "bu kişiden bir şey bekliyor muyuz" sorusunu yine
     * yanıtlayamazdı. Bant sekmeye girmeden görünür.
     *
     * ⚠️ Süre geçtiğinde bant KIRMIZIYA döner ve o kadar: kart askıya
     * alınmaz, erişim kesilmez. Kararı okuyan kişi verir.
     */
    public function uyariBandi(): ?array
    {
        $talep = $this->acikTalep();

        if ($talep === null) {
            return null;
        }

        $kalan = $talep->kalanGun();
        $gecti = $talep->suresiGectiMi();

        $sure = match (true) {
            $kalan === null => '',
            $gecti => sprintf(' — süresi %d gün önce doldu, kararı siz verin', abs($kalan)),
            $kalan === 0 => ' — süre bugün doluyor',
            default => sprintf(' — son gün %s (%d gün kaldı)',
                $talep->son_tarih->timezone('Europe/Istanbul')->format('d.m.Y'), $kalan),
        };

        return [
            'renk' => $gecti ? 'danger' : 'warning',
            'ikon' => 'heroicon-m-document-plus',
            'baslik' => 'Belge bekleniyor',
            'metin' => 'Bu akreditasyon için belge istendi'.$sure.'. '
                .'Kart aktif kalmaya devam ediyor; kişi yükleme bağlantısıyla gönderebilir.',
            'baglanti' => [
                'etiket' => 'Başvuru detayına git',
                'url' => BasvuruResource::getUrl('inceleme', ['record' => $this->kayit()->basvuru]),
            ],
        ];
    }

    /** Karar sonrası açılmış, henüz yanıtlanmamış belge talebi. */
    private function acikTalep(): ?BasvuruDuzeltmesi
    {
        return $this->kayit()->basvuru?->acikBelgeTalebi();
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
                    // Açık belge talebi: ne istendi, ne zamana kadar.
                    'talep' => $this->acikTalep(),
                ],
            ],
        ];
    }

    /**
     * Detay sayfası artık İŞ GÖRÜYOR -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 💀 Sayfa yalnızca "Kart PDF indir" taşıyordu: yetkili kişinin
     * akreditasyonunu okuyup karar veriyor, sonra kararı uygulamak için
     * listeye geri dönüp satır menüsünü açmak zorunda kalıyordu. Kurumda bu
     * sorun çözülmüştü, kişide duruyordu.
     *
     * 🔑 Eylemler KOPYALANMADI, ortak tanımdan geliyor (AkreditasyonEylemleri):
     * aynı üç karar hem listede hem burada aynı kip metni, aynı yetki ve aynı
     * durum koşuluyla veriliyor.
     */
    protected function getHeaderActions(): array
    {
        return [
            AkreditasyonEylemleri::askiyaAl(),
            AkreditasyonEylemleri::askiyiKaldir(),

            /*
             * 💀 BURASI ESKİDEN SADECE BİR BAĞLANTIYDI ("Ek evrak talep et" →
             * inceleme ekranı) ve yetkiliyi çıkmaza sokuyordu: inceleme
             * ekranında "Belge iste" karara bağlanmış başvuruda pasif, tooltip
             * de "önce Akreditasyonu geri al" diyor. O adım kartı GERİ
             * ALINAMAZ biçimde iptal edip bütün onay turunu baştan başlatıyor.
             * Tek bir eksik belge için ödenen bedel buydu.
             *
             * 🔑 Talep artık burada, akreditasyona dokunmadan açılıyor
             * (BasvuruAkisi::belgeTalepEt). Karar ÖNCESİ düzeltme hâlâ inceleme
             * ekranının işi -- iki ekran iki farklı soruyu yanıtlıyor.
             */
            BelgeTalebiEylemi::akreditasyon(fn () => $this->kayit()),

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

            // 🔻 Geri alınamaz: en sonda dursun.
            AkreditasyonEylemleri::iptalEt(),
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
