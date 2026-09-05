<?php

namespace App\Filament\Yonetim\Ortak;

use App\Models\DenetimKaydi;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Yönetim panelindeki her kaydın detay sayfası için ortak iskelet -- S1.
 *
 * 🔑 KURAL: listede görünen her kaydın kalıcı adresi olan bir detay sayfası
 * olacak. Tablo listeler, detay anlatır. On kaynaktan yalnız biri (başvuru
 * inceleme) detay açıyordu; gerisi okuma ekranıydı.
 *
 * Şablon bir kez burada duruyor ki dokuz ayrı tasarım çıkmasın:
 *   1. Başlık şeridi -- birincil kimlik, durum rozeti, birincil eylem
 *   2. Künye bloğu   -- iki kolon, kopyalanabilir alanlar
 *   3. İlişkili kayıtlar -- sekmeler
 *   4. Denetim izi   -- her sayfanın en altında, aynı biçimde
 *   5. Listeye dönüş -- filtre ve sayfa numarası korunarak
 *
 * 🔒 Yetki: her detay sayfası ilgili policy'nin view() metodundan geçer.
 * Menüyü gizlemek yetki değildir -- bu kural depoda MedyaMerkeziSayfasi'nda
 * zaten yazılı, aynısı burada.
 */
abstract class DetaySayfasi extends Page
{
    /*
     * 🪤 Kayıt taşıyan özel kaynak sayfası bu trait'i KULLANMAK ZORUNDA;
     * yoksa Filament kayda ulaşamayıp sayfayı sessizce 404 yapıyor.
     */
    use InteractsWithRecord;

    protected string $view = 'filament.yonetim.ortak.detay';

    /**
     * Listeye dönüş adresi. Tarayıcının geldiği adres saklanır ki listedeki
     * süzgeç, sıralama ve sayfa numarası KORUNSUN -- yoksa yetkili her
     * detaydan sonra filtreyi baştan kuruyor.
     */
    public ?string $donusAdresi = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $liste = static::getResource()::getUrl('index');
        $onceki = url()->previous();

        $this->donusAdresi = str_starts_with($onceki, $liste) ? $onceki : $liste;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('view', $this->getRecord()) ?? false, 403);
    }

    /** Başlık şeridindeki birincil kimlik: kart no, ünvan, ad. */
    abstract public function kimlik(): string;

    /** Kimliğin altındaki ikincil satır (kişi adı, kurum, e-posta…). */
    public function altBaslik(): ?string
    {
        return null;
    }

    /** Durum rozeti: ['etiket' => 'Aktif', 'renk' => 'success'] ya da null. */
    public function durumRozeti(): ?array
    {
        return null;
    }

    /**
     * Künyenin HEMEN ALTINDAKİ uyarı bandı -- sekmeye girmeden görülmesi
     * gereken şeyler için.
     *
     * 💀 "Eksik evrak bekleniyor" gibi bir bilgi sekmenin içinde durursa
     * yetkili o sekmeye girmedikçe görmez; kurum aylarca belge yüklemeden
     * bekler ve kimse fark etmez. Bant sayfayı açan herkesin gözüne girer.
     *
     * ['renk' => 'warning', 'baslik' => '…', 'metin' => '…', 'ikon' => '…',
     *  'baglanti' => ['etiket' => '…', 'url' => '…']] ya da null.
     */
    public function uyariBandi(): ?array
    {
        return null;
    }

    /**
     * Künye alanları. Değer null ise satır "—" basar.
     * ['E-posta' => ['deger' => 'a@b.c', 'kopyala' => true], 'İl' => 'Çorum']
     */
    abstract public function kunye(): array;

    /**
     * İlişkili kayıt sekmeleri:
     * ['kart' => ['baslik' => 'Kart', 'view' => 'filament...', 'veri' => [...]]]
     */
    public function sekmeler(): array
    {
        return [];
    }

    /** Denetim izinde kaç kayıt gösterilsin. */
    protected int $denetimSiniri = 20;

    public function denetimKayitlari(): Collection
    {
        $kayit = $this->getRecord();

        return DenetimKaydi::query()
            ->where('kayit_tipi', $kayit::class)
            ->where('kayit_id', $kayit->getKey())
            ->latest('id')
            ->limit($this->denetimSiniri)
            ->get();
    }

    public function getRecordModel(): Model
    {
        return $this->getRecord();
    }
}
