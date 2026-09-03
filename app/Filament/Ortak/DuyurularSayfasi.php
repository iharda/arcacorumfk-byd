<?php

namespace App\Filament\Ortak;

use App\Models\Duyuru;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Kulüp duyuruları — akredite kullanıcı görünümü.
 *
 * 🔑 Sayfanın işi TEK: kullanıcı başlıkları tarasın, ilgilendiğini açsın.
 * Tam metin, görsel ve video listeden çıktı; detayda kuruluyor. Eskiden on
 * duyurunun tamamı tam boy basılıyordu -- tek sayfada on `<video>` elemanı.
 */
abstract class DuyurularSayfasi extends MedyaMerkeziSayfasi
{
    use WithPagination;

    protected string $view = 'filament.ortak.duyurular';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Duyurular';

    protected static ?string $title = 'Kulüp duyuruları';

    protected static ?int $navigationSort = 10;

    #[Url(as: 'ara', keep: false)]
    public string $arama = '';

    /**
     * 🔑 Detay için AYRI ROTA YOK: açık kayıt adres çubuğunda taşınıyor
     * (`/panel/duyurular?acik=01J8…`). Derin bağlantı çalışır, mobil
     * bildirimi buraya yollanabilir, bakılacak ikinci rota doğmaz.
     */
    #[Url(as: 'acik')]
    public ?string $acikUlid = null;

    protected static function gorulmeAlani(): ?string
    {
        return 'duyuru_gorulme_at';
    }

    public function updatedArama(): void
    {
        $this->resetPage();
    }

    public function aramayiTemizle(): void
    {
        $this->arama = '';
        $this->resetPage();
    }

    public function getDuyurularProperty(): LengthAwarePaginator
    {
        return Duyuru::query()
            /*
             * 🔑 `icerik` LİSTEDE OKUNMAZ: zengin metin kolonu büyük, on iki
             * kaydın tamamı için çekmenin anlamı yok. Özet boşsa satırda
             * gösterilecek metin de özetten değil, detaydan gelir.
             */
            ->select(['id', 'ulid', 'baslik', 'ozet', 'gorsel_yolu', 'video_yolu', 'yayin_at'])
            ->yayinda()
            ->when(filled($this->arama), fn (Builder $sorgu) => $sorgu
                ->where(fn (Builder $alt) => $alt
                    // PostgreSQL: `ilike` büyük/küçük harf ayırmaz.
                    ->where('baslik', 'ilike', '%'.$this->arama.'%')
                    ->orWhere('ozet', 'ilike', '%'.$this->arama.'%')))
            ->latest('yayin_at')
            ->paginate(12);
    }

    /** Adres çubuğunda `acik` varsa o duyuru; yoksa null. */
    public function getAcikDuyuruProperty(): ?Duyuru
    {
        if (blank($this->acikUlid)) {
            return null;
        }

        // 🔒 Yayında OLMAYAN bir ulid adrese elle yazılırsa 404 -- liste
        // açılmaz, kaydın varlığı da sızmaz.
        return Duyuru::query()->yayinda()->where('ulid', $this->acikUlid)->firstOrFail();
    }

    /**
     * Yayın tarihi SON BAKIŞTAN sonraysa "Yeni".
     *
     * 🔑 Hiç bakılmamışsa (eşik null) hiçbir şey yeni sayılmaz: rozetin anlamı
     * "son bakışından beri yayınlandı". İlk ziyarette listenin tamamını kırmızı
     * rozetle doldurmak, kullanıcının zaten okumak için açtığı ekranda
     * gürültüden başka bir şey değil.
     */
    public function yeniMi(Duyuru $duyuru): bool
    {
        return $this->esik !== null
            && $duyuru->yayin_at !== null
            && $duyuru->yayin_at->gt($this->esik);
    }
}
