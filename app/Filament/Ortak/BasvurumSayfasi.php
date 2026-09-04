<?php

namespace App\Filament\Ortak;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruUygunlugu;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Başvurum" sayfasının ortak gövdesi — kurum ve üye panelleri aynı ekranı
 * kullanır.
 *
 * 🔑 SALT OKUNUR (Revizyon md.3.6). Evrak artık başvuru formunda alınıyor,
 * eksik evrak da panelsiz düzeltme bağlantısından tamamlanıyor; hesap ise
 * ancak ONAY sonrası açılıyor. Yani bu sayfaya ulaşan herkesin başvurusu
 * çoktan karara bağlanmıştır — burada yüklenecek bir şey kalmaz, geçmiş
 * görünür.
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
