<?php

namespace App\Filament\Ortak;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\BasvuruDuzeltmesi;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\BasvuruUygunlugu;
use App\Support\DuzeltmeAlanlari;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Başvurum" sayfasının ortak gövdesi — kurum ve üye panelleri aynı ekranı
 * kullanır.
 *
 * Büyük ölçüde SALT OKUNUR (Revizyon md.3.6): evrak başvuru formunda alınır,
 * hesap da ancak ONAY sonrası açılır.
 *
 * 🔑 TEK İSTİSNA EKSİK EVRAK (Cüneyt Bey revizyonu 05.09.2026). Onaylanmış bir
 * kurumun kararı geri alınıp belge istendiğinde kurumun hesabı ARTIK VARDIR
 * ama panelinde yükleyecek yer yoktu: tek yol e-postayla giden tek kullanımlık
 * bağlantıydı. Posta silinmiş ya da başka birine gitmişse kuruluş çıkmaz
 * sokaktaydı -- kulüp bekliyor, kuruluş yükleyemiyor.
 *
 * 🪤 Panelde AYRI BİR YÜKLEME FORMU YAZILMADI: düzeltme akışının kendi alan
 * kuralları, evrak doğrulaması ve tur kaydı var (BasvuruDuzeltmeController).
 * İkinci bir yol açmak o kuralların kopyalanması demekti. Panel yalnızca
 * kişinin KENDİ başvurusu için taze bir bilet üretip aynı akışa sokar.
 *
 * Panel başına ayrı ince bir alt sınıf var; Filament sayfaları panelin kendi
 * dizininden keşfediyor.
 */
abstract class BasvurumSayfasi extends Page
{
    protected string $view = 'filament.ortak.basvurum';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Başvurum';

    protected static ?string $title = 'Başvurum';

    protected static ?int $navigationSort = 1;

    public ?Basvuru $basvuru = null;

    public function mount(): void
    {
        $this->basvuruyuYukle();

        abort_if($this->basvuru === null, 404);
    }

    private function basvuruyuYukle(): void
    {
        $this->basvuru = Basvuru::with(['evraklar.turu', 'kurum', 'akreditasyon'])
            ->where('kullanici_id', Auth::id())
            ->latest('id')
            ->first();
    }

    public function eksikEvrakBekliyorMu(): bool
    {
        return $this->basvuru?->durum === BasvuruDurumu::EksikEvrak;
    }

    /**
     * KARAR SONRASI belge talebi -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 💀 Akredite kişiden belge istenebilir hâle gelince bu sayfa onu HİÇ
     * göstermiyordu: tek ölçüt `durum === eksik_evrak` idi, belge talebinde
     * ise durum `onaylandi` kalıyor. Kişi e-postayı silmişse yükleyecek yer
     * bulamazdı -- kurum tarafında düzeltilen çıkmazın (md.3.6) aynısı.
     */
    public function belgeTalebiBekliyorMu(): bool
    {
        return $this->belgeTalebi() !== null;
    }

    public function belgeTalebi(): ?BasvuruDuzeltmesi
    {
        return $this->basvuru?->acikBelgeTalebi();
    }

    /** Şeritte yazılan süre cümlesi; son tarih yoksa null. */
    public function belgeTalebiSuresi(): ?string
    {
        $talep = $this->belgeTalebi();

        if ($talep?->son_tarih === null) {
            return null;
        }

        $tarih = $talep->son_tarih->timezone('Europe/Istanbul')->format('d.m.Y');
        $kalan = $talep->kalanGun();

        return match (true) {
            $talep->suresiGectiMi() => 'Son gönderim tarihi '.$tarih.' idi; lütfen en kısa sürede gönderin.',
            $kalan === 0 => 'Son gönderim tarihi bugün ('.$tarih.').',
            default => 'Lütfen '.$tarih.' tarihine kadar gönderin ('.$kalan.' gün kaldı).',
        };
    }

    /**
     * İstenen kalemlerin okunur listesi -- uyarı şeridinde kullanılır.
     *
     * @return array<int, string>
     */
    public function getIstenenKalemlerProperty(): array
    {
        if (! $this->eksikEvrakBekliyorMu() && ! $this->belgeTalebiBekliyorMu()) {
            return [];
        }

        return collect(array_keys($this->basvuru->duzeltme_notlari ?? []))
            ->map(fn (string $anahtar) => DuzeltmeAlanlari::etiket($this->basvuru, $anahtar))
            ->values()
            ->all();
    }

    /**
     * "Eksik evrağı yükle" -- kişiyi kendi düzeltme formuna sokar.
     *
     * 🔒 Bilet YALNIZCA sayfanın sahibine üretilir: `$this->basvuru` zaten
     * `kullanici_id = Auth::id()` ile yüklendi, başka bir başvuruya bilet
     * üretilemez. Durum kontrolü de ayrıca yapılır ki karara bağlanmış bir
     * başvuruya bilet çıkmasın.
     *
     * ⚠️ Yeni bilet ESKİSİNİ İPTAL EDER (BasvuruBiletiAkisi::uret). Bu bilerek:
     * tek anda tek geçerli bağlantı olsun. Kullanıcıya da yazıyoruz.
     */
    public function eksikEvrakAction(): Action
    {
        return Action::make('eksikEvrak')
            ->label(fn () => $this->belgeTalebiBekliyorMu() ? 'İstenen belgeyi gönder' : 'Eksik evrağı yükle')
            ->icon('heroicon-m-arrow-up-tray')
            ->color('warning')
            ->visible(fn () => $this->eksikEvrakBekliyorMu() || $this->belgeTalebiBekliyorMu())
            ->action(function () {
                if (! $this->eksikEvrakBekliyorMu() && ! $this->belgeTalebiBekliyorMu()) {
                    return null;
                }

                $token = app(BasvuruBiletiAkisi::class)->uret(
                    $this->basvuru,
                    $this->belgeTalebiBekliyorMu() ? 'belge_talebi' : 'eksik_evrak',
                );

                return redirect()->to(route('basvuru.duzelt', $token));
            });
    }

    /** Menüde yalnızca başvurusu olanlara görünsün. */
    public static function shouldRegisterNavigation(): bool
    {
        return Basvuru::where('kullanici_id', Auth::id())->exists();
    }

    /**
     * BU BAŞVURUYA ait evrak türleri.
     *
     * 💀 Eskiden o anki BÜTÜN aktif türler listeleniyordu. Sonradan yeni bir
     * zorunlu belge eklendiğinde (imza sirküleri, M7) ONAYLANMIŞ eski
     * başvurularda o satır "Bekliyor" diye beliriyordu: kurum kendi panelinde
     * hiç istenmemiş bir belgeyi eksik görüyor, yükleyecek bir yer de yok
     * (yükleme yalnız düzeltme biletiyle açılır). Çıkmaz sokak ve yanlış bilgi.
     *
     * 🔑 Gösterilecek iki küme: (1) bu başvuruda GERÇEKTEN yüklenmiş belgeler,
     * (2) bu başvuru için hâlâ zorunlu olanlar -- düzeltme turundaki biri neyi
     * tamamlaması gerektiğini görmeye devam etsin. Sonradan eklenmiş ve bu
     * başvuruyu ilgilendirmeyen türler listeye hiç girmez.
     *
     * @return Collection<int, EvrakTuru>
     */
    public function getEvrakTurleriProperty()
    {
        $yuklenenler = $this->basvuru->evraklar->pluck('evrak_turu_id')->all();

        return EvrakTuru::turIcin($this->basvuru->tur)
            ->filter(fn (EvrakTuru $tur) => in_array($tur->id, $yuklenenler, true)
                || $tur->basvuruIcinZorunluMu($this->basvuru))
            ->values();
    }

    /**
     * Reddedilen başvurudan sonra yeniden başvuru. Kapalı bir kapı bırakmamak
     * için: red gerekçesini okuyan kişi aynı ekrandan yeni başvuruya geçebilir.
     * Yeni başvuru da kamuya açık formdan yapılır — panelde form yok.
     *
     * @return array{adres: ?string, engel: ?string}
     */
    public function getYenidenBasvuruProperty(): array
    {
        if ($this->basvuru->durum !== BasvuruDurumu::Reddedildi) {
            return ['adres' => null, 'engel' => null];
        }

        if ($engel = app(BasvuruUygunlugu::class)->engel(Auth::user(), $this->basvuru->tur)) {
            return ['adres' => null, 'engel' => $engel];
        }

        return ['adres' => $this->basvuru->tur->basvuruRotasi(), 'engel' => null];
    }
}
