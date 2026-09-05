<?php

namespace App\Filament\Yonetim\Resources\Basvurus\Pages;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
use App\Filament\Yonetim\Ortak\TalepAlanlari;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Notifications\EksikEvrakTalebi;
use App\Servisler\BasvuruAkisi;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\DegerlendirmeAkisi;
use App\Servisler\KurumAkreditasyonu;
use App\Support\DuzeltmeAlanlari;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Başvuru inceleme ekranı -- Plan v1.0 md.8.
 * Solda başvuru verisi ve evrak listesi, sağda seçili evrakın önizlemesi;
 * kararlar üst çubukta.
 */
class Inceleme extends Page
{
    /*
     * 🪤 Kayıt taşıyan özel bir kaynak sayfası bu trait'i KULLANMAK ZORUNDA.
     * Kendi `public Basvuru $record` özelliğini tanımlayıp mount()'ta elle
     * doldurmak yetmiyor: Filament kayda `getRecord()` ile ulaşıyor ve trait
     * yoksa sayfa sessizce 404 dönüyor.
     */
    use InteractsWithRecord;

    protected static string $resource = BasvuruResource::class;

    protected string $view = 'filament.yonetim.basvuru-inceleme';

    protected static ?string $title = 'Başvuru incelemesi';

    public function mount(string|int $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorize('view', $this->record);

        $this->record->load(['kurum', 'kullanici', 'evraklar.turu', 'inceleyen', 'kararVeren']);
    }

    public function getTitle(): string|Htmlable
    {
        // Hesap onaya kadar yok: ad başvurunun üstünden okunur.
        return $this->record->kurum?->resmi_unvan ?? $this->record->basvuranAdi();
    }

    public function getSubheading(): string|Htmlable|null
    {
        // Gönderilmemiş başvuruda numara yoktur (T3); ayraç boşta kalmasın.
        return implode(' · ', array_filter([$this->record->tur->etiket(), $this->record->basvuru_no]));
    }

    /*
     * 🗑️ `evrakSec()` / `seciliEvrakModeli` KALDIRILDI (M6.3 md.3).
     * Evrak seçimi Livewire gidiş-dönüşüyle yapılıyordu: her tıklama sunucuya
     * gidiyor, sayfa yeniden çiziliyordu. Seçim artık `<x-parcalar.evrak-listesi>`
     * içinde Alpine ile ve anında; ok tuşlarıyla da geziliyor.
     */

    /**
     * Aynı kişinin ÖNCEKİ başvuruları. Reddedilen ya da ayrılan biri yeniden
     * başvurabildiği için yetkili "bunu daha önce görmüş müydük" sorusunun
     * cevabını ekranda görmeli.
     *
     * @return Collection<int, Basvuru>
     */
    public function getGecmisBasvurularProperty()
    {
        $eposta = $this->record->basvuranEpostasi();

        if ($eposta === null) {
            return collect();
        }

        /*
         * 🔑 Bağ E-POSTA üzerinden kurulur: hesap onay anında açıldığı için
         * (Revizyon md.3.2) kişinin eski başvurularının çoğunda `kullanici_id`
         * BOŞTUR. Yalnızca hesaba baksaydık "bunu daha önce görmüş müydük"
         * sorusunun cevabı hep boş çıkardı.
         */
        return Basvuru::query()
            ->where(fn (Builder $sorgu) => $sorgu
                ->where('basvuran_eposta', $eposta)
                ->when(
                    $this->record->kullanici_id,
                    fn (Builder $alt, int $id) => $alt->orWhere('kullanici_id', $id),
                ))
            ->whereKeyNot($this->record->getKey())
            ->latest('id')
            ->get();
    }

    /**
     * Bu başvurunun hedefine (kuruma ya da kişiye) verilmiş güncel puan.
     *
     * 🔒 Yalnızca yönetim panelinde okunur; şablon da `@can` ile sarmalı.
     */
    public function getDegerlendirmeProperty(): ?Degerlendirme
    {
        if (! auth()->user()?->can('degerlendirme.yonet')) {
            return null;
        }

        return app(DegerlendirmeAkisi::class)->basvuruIcin($this->record);
    }

    /**
     * Bireysel başvuranın KURUMUNA verilmiş puan -- salt okunur ikinci satır.
     * Yetkili kişiyi değerlendirirken kurumun geçmişini de görsün.
     */
    public function getKurumDegerlendirmesiProperty(): ?Degerlendirme
    {
        if ($this->record->tur === BasvuruTuru::Kurum
            || ! auth()->user()?->can('degerlendirme.yonet')) {
            return null;
        }

        return app(DegerlendirmeAkisi::class)->kurumIcin($this->record->kurum);
    }

    /**
     * Puanlama modalı. 🔑 ÜST ÇUBUKTA DEĞİL sayfa içinde: üst çubuk KARAR
     * eylemlerinin yeri, değerlendirme ise karar değildir (briefi md. A.1).
     */
    public function degerlendirAction(): Action
    {
        return DegerlendirmeEylemi::basvuru(fn () => $this->record);
    }

    /**
     * Eksik evrak talebinde işaretlenebilecek alanlar: anahtar => etiket.
     *
     * 🔑 Anahtarlar `DuzeltmeAlanlari` şemasından gelir (`evrak:<kod>`,
     * `veri:<alan>`) -- görünen ad DEĞİL. Evrak türünün adı panelden
     * değiştiğinde yoldaki biletler bozulmasın (Düzeltme listesi md.11).
     */
    public function isaretlenebilirAlanlar(): array
    {
        return DuzeltmeAlanlari::tumu($this->record);
    }

    /** Onay kipinde gösterilen bölge cümlesi. */
    private function bolgeOzeti(): string
    {
        $varsayilan = (array) Ayar::al('varsayilan_bolgeler', []);

        if ($varsayilan === []) {
            return 'Kart HER KAPIDAN geçerli olacak (Ayarlar\'da varsayılan bölge tanımlı değil).';
        }

        $adlar = collect((array) Ayar::al('bolgeler', []))
            ->only($varsayilan)
            ->values()
            ->implode(', ');

        return 'Kart şu bölgelere yetkili olacak: '.($adlar ?: implode(', ', $varsayilan)).'.';
    }

    /**
     * Uygulanamayan aksiyonun SEBEBİ -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 💀 Aksiyonlar duruma göre EKRANDAN KALKIYORDU: yetkili karara bağlanmış
     * bir başvuruyu açtığında hiçbir düğme göremiyor, "ben bunu neden
     * yapamıyorum" sorusunun cevabı hiçbir yerde yazmıyordu. Düğmeler artık
     * hep duruyor; uygulanamıyorsa pasif ve sebebi fare üstüne gelince yazılı.
     */
    public function pasifSebebi(string $kural): ?string
    {
        if (auth()->user()->can($kural, $this->record)) {
            return null;   // uygulanabiliyor; açıklamaya gerek yok
        }

        return match (true) {
            in_array($this->record->durum, [
                BasvuruDurumu::Onaylandi,
                BasvuruDurumu::Reddedildi,
                BasvuruDurumu::IptalEdildi,
            ], true) => 'Başvuru karara bağlandı ('.$this->record->durum->etiket()
                .'). Yeniden işlem yapmak için önce "Akreditasyonu geri al" deyin.',

            $kural === 'incele' => 'Başvuru zaten incelemeye alınmış.',
            $kural === 'eksikEvrakIste' => 'Belge istemek için başvuru "İnceleniyor" durumunda olmalı.',
            $kural === 'kararVer' => 'Karar vermek için başvuruyu önce incelemeye alın.',

            default => 'Bu adım başvurunun şu anki durumunda uygulanamıyor.',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            /*
             * 🔑 KARARI GERİ AL. Onaylandı/Reddedildi/İptal edildi eskiden
             * bitiş durumuydu; yanlış karar verildiğinde tek çıkış
             * veritabanına elle müdahaleydi. Kart iptali, rol geri alma ve
             * kurum durumunun düzeltilmesi servis tarafında TEK işlemde
             * (BasvuruAkisi::karariGeriAl).
             */
            Action::make('karariGeriAl')
                ->label('Akreditasyonu geri al')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => auth()->user()->can('karariGeriAl', $this->record))
                ->schema([
                    Textarea::make('gerekce')
                        ->label('Gerekçe')
                        ->required()
                        ->rows(3)
                        ->maxLength(500),

                    /*
                     * 💀 Kurumun akreditasyonu buradan da düşüyor ama
                     * ÇALIŞANLARIN KARTLARI aktif kalıyordu: akreditasyonu
                     * düşmüş kuruluşun muhabiri turnikeden geçmeye devam
                     * ediyordu. "Akreditasyonu kaldır" ekranı bu kararı sayıyla
                     * birlikte soruyor; aynı sonucu doğuran bu adım hiç
                     * sormuyordu. (M9 №1'in aynısı, öbür kapıda.)
                     */
                    Toggle::make('kartlari_askiya_al')
                        ->label('Çalışanların kartlarını da askıya al')
                        ->helperText(fn () => "Bu kurumun {$this->etkilenenKartSayisi()} aktif kartı var; "
                            .'kapatılmazsa turnikeden geçmeye devam ederler. Askı geri alınabilir, '
                            .'iptalden farklı olarak kalıcı değildir.')
                        ->default(true)
                        ->visible(fn () => $this->etkilenenKartSayisi() > 0),
                ])
                ->modalHeading('Akreditasyonu geri al')
                ->modalDescription('Başvuru "İnceleniyor" durumuna döner ve yeniden karar verilebilir. '
                    .'Üretilmiş kart İPTAL EDİLİR, verilen akreditasyon rolü geri alınır; kurumsal '
                    .'başvuruda kurumun akreditasyonu da düşer. Hesap silinmez, erişimi kapanır.')
                ->modalSubmitActionLabel('Akreditasyonu geri al')
                ->action(fn (array $data) => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->karariGeriAl(
                        $this->record,
                        $data['gerekce'],
                        (bool) ($data['kartlari_askiya_al'] ?? false),
                    ),
                    'Karar geri alındı; başvuru yeniden incelemenizde.',
                )),

            Action::make('incelemeyeAl')
                ->label('İncelemeye al')
                ->icon('heroicon-m-eye')
                ->visible(fn () => auth()->user()->can('basvuru.incele'))
                ->disabled(fn () => ! auth()->user()->can('incele', $this->record))
                ->tooltip(fn () => $this->pasifSebebi('incele'))
                ->action(fn () => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->incelemeyeAl($this->record),
                    'Başvuru incelemenize alındı.',
                )),

            Action::make('eksikEvrak')
                ->label('Belge iste')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Başvurandan belge veya bilgi isteyin')
                ->modalSubmitActionLabel('Talebi gönder')
                ->modalCancelActionLabel('Vazgeç')
                ->visible(fn () => auth()->user()->can('basvuru.incele'))
                ->disabled(fn () => ! auth()->user()->can('eksikEvrakIste', $this->record))
                ->tooltip(fn () => $this->pasifSebebi('eksikEvrakIste'))
                ->schema([
                    /*
                     * Alanlar ORTAK tanımdan (TalepAlanlari): aynı iki liste
                     * akreditasyon detayındaki belge talebinde de kullanılıyor.
                     * Liste kısa (~15) -- arama kutusu yerine düz açılır liste.
                     */
                    TalepAlanlari::kalemler(
                        fn () => $this->isaretlenebilirAlanlar(),
                        etiket: 'Düzeltilmesi istenen alanlar',
                        ekleEtiketi: 'Alan ekle',
                    ),
                    TalepAlanlari::ekTalep(),
                    Textarea::make('mesaj')
                        ->label('Ek not (isteğe bağlı)')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data, Action $action) {
                    /*
                     * 💀 Üstteki liste `minItems(1)` idi ve yalnızca EK TALEP
                     * göndermek isteyeni kilitliyordu: "en az bir öğe
                     * içermelidir" hatası veriyor, çıkış yolunu söylemiyordu
                     * (İbrahim Bey, 05.09.2026). Zorunluluk artık iki listeye
                     * BİRDEN bakıyor; `halt()` modalı açık tutuyor ki yazılan
                     * başlık ve açıklama kaybolmasın.
                     */
                    if ($hata = TalepAlanlari::kalemHatasi($data['notlar'] ?? null, $data['ek_talepler'] ?? null)) {
                        Notification::make()->title($hata)->danger()->send();

                        $action->halt();
                    }

                    $this->calistir(
                        fn () => app(BasvuruAkisi::class)->eksikEvrakIste(
                            $this->record,
                            TalepAlanlari::kalemleriTopla($data['notlar'] ?? null),
                            $data['mesaj'] ?? null,
                            TalepAlanlari::ekTalepleriTopla($data['ek_talepler'] ?? null),
                        ),
                        'Düzeltme talebi gönderildi.',
                    );
                }),

            /*
             * Düzeltme bağlantısı e-postaya ulaşmadıysa ya da süresi dolduysa
             * başvuran çıkmazda kalmasın: yetkili yenisini gönderebilir.
             * Eski bağlantı ölür.
             */
            Action::make('duzeltmeBaglantisi')
                ->label('Düzeltme bağlantısını yeniden gönder')
                ->icon('heroicon-m-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Düzeltme bağlantısını yeniden gönder')
                ->modalDescription('Başvurana yeni bir bağlantı gider ve önceki bağlantı geçersiz olur.')
                ->modalSubmitActionLabel('Gönder')
                ->modalCancelActionLabel('Vazgeç')
                ->visible(fn () => $this->record->durum === BasvuruDurumu::EksikEvrak
                    && auth()->user()->can('eksikEvrakIste', $this->record))
                ->action(fn () => $this->calistir(function () {
                    $token = app(BasvuruBiletiAkisi::class)->yenidenGonder($this->record);

                    $this->record->bildirimHedefi()->notify(new EksikEvrakTalebi($this->record, $token));
                }, 'Yeni düzeltme bağlantısı gönderildi.')),

            /*
             * Onay kipinin metni Cüneyt Bey revizyonunda (03.09.2026) yeniden
             * yazıldı: başlık SORU, açıklama onaydan sonra ne olacağını
             * anlatıyor, düğmeler "Vazgeç" / "Başvuruyu onayla".
             */
            Action::make('onayla')
                ->label('Onayla')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                // Hesap onayla birlikte açılır; bireysel başvuruda "kurum
                // akredite edilecek" metni yanlıştı.
                ->modalHeading(fn () => $this->record->tur === BasvuruTuru::Kurum
                    ? 'Medya kuruluşunu onaylamak istiyor musunuz?'
                    : 'Başvuruyu onaylamak istiyor musunuz?')
                ->modalDescription(fn () => $this->record->tur === BasvuruTuru::Kurum
                    ? 'Onaylamanızın ardından medya kuruluşu sisteme kaydedilecek, başvuru '
                        .'yetkilisi için bir hesap oluşturulacak ve sonuç e-posta yoluyla bildirilecektir.'
                    // 🔑 Kartın hangi bölgelere yetkili doğacağı KARAR ANINDA
                    // görünmeli (Düzeltme listesi md.9): "her kapıdan geçer"
                    // sessizce verilen bir yetki olmasın.
                    : 'Onaylamanızın ardından başvuru sahibi için bir hesap açılacak, akreditasyon '
                        .'kaydı ve kart numarası oluşturulacaktır. '.$this->bolgeOzeti())
                ->modalSubmitActionLabel(fn () => $this->record->tur === BasvuruTuru::Kurum
                    ? 'Kuruluşu onayla'
                    : 'Başvuruyu onayla')
                ->modalCancelActionLabel('Vazgeç')
                ->visible(fn () => auth()->user()->can('basvuru.karar'))
                ->disabled(fn () => ! auth()->user()->can('kararVer', $this->record))
                ->tooltip(fn () => $this->pasifSebebi('kararVer'))
                ->action(fn () => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->onayla($this->record),
                    'Başvuru onaylandı.',
                )),

            Action::make('reddet')
                ->label('Reddet')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->modalHeading('Başvuruyu reddetmek istiyor musunuz?')
                ->modalSubmitActionLabel('Başvuruyu reddet')
                ->modalCancelActionLabel('Vazgeç')
                ->visible(fn () => auth()->user()->can('basvuru.karar'))
                ->disabled(fn () => ! auth()->user()->can('kararVer', $this->record))
                ->tooltip(fn () => $this->pasifSebebi('kararVer'))
                ->schema([
                    Textarea::make('gerekce')
                        ->label('Red gerekçesi')
                        ->helperText('Bu metin başvurana aynen iletilir.')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->reddet($this->record, $data['gerekce']),
                    'Başvuru reddedildi.',
                )),

            /*
             * "İptal edildi" durumu -- Cüneyt Bey revizyonu (03.09.2026).
             * 🔑 REDDETMEKTEN AYRI: red bir karardır, başvurana gerekçesiyle
             * bildirilir ve yeniden başvuru bekleme süresini işletir. İptal
             * kaydın kapatılmasıdır (mükerrer başvuru, başvuranın vazgeçmesi,
             * yanlış türde başvuru) ve bildirim doğurmaz.
             */
            Action::make('iptalEt')
                ->label('İptal et')
                ->icon('heroicon-m-archive-box-x-mark')
                ->color('gray')
                ->modalHeading('Başvuruyu iptal etmek istiyor musunuz?')
                ->modalDescription('Başvuru kuyruktan düşer ve karara bağlanamaz. '
                    .'Başvurana bildirim GİTMEZ; iptali kendisine siz haber vermelisiniz.')
                ->modalSubmitActionLabel('Başvuruyu iptal et')
                ->modalCancelActionLabel('Vazgeç')
                ->visible(fn () => auth()->user()->can('basvuru.karar'))
                ->disabled(fn () => ! auth()->user()->can('iptalEt', $this->record))
                ->tooltip(fn () => $this->pasifSebebi('iptalEt'))
                ->schema([
                    Textarea::make('gerekce')
                        ->label('İptal sebebi')
                        ->helperText('Yalnızca denetim kaydına yazılır, başvurana gönderilmez.')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->calistir(
                    fn () => app(BasvuruAkisi::class)->iptalEt($this->record, $data['gerekce']),
                    'Başvuru iptal edildi.',
                )),
        ];
    }

    /**
     * Karar geri alınırsa kaç ÇALIŞAN kartı etkilenir?
     *
     * Yalnızca kurumsal onayda anlamlı: kurum akredite değilse geri alma
     * kurumun akreditasyonunu zaten düşürmez, kartlara da dokunmaz.
     */
    public function etkilenenKartSayisi(): int
    {
        if ($this->record->tur !== BasvuruTuru::Kurum
            || $this->record->kurum?->akreditasyon_durumu !== 'akredite') {
            return 0;
        }

        return app(KurumAkreditasyonu::class)->aktifKartSayisi($this->record->kurum);
    }

    /** Akış çağrılarını tek yerde sarmalar: hata bildirimi + kaydı tazeleme. */
    private function calistir(callable $is, string $basariMesaji): void
    {
        try {
            $is();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh()->load(['evraklar.turu', 'inceleyen', 'kararVeren', 'kurum', 'kullanici']);

        Notification::make()->title($basariMesaji)->success()->send();
    }

    public function getDurumRozeti(): array
    {
        return [
            // Üçü de kararın bugünkü karşılığıyla (bkz. Basvuru::durumEtiketi).
            'etiket' => $this->record->durumEtiketi(),
            'renk' => $this->record->durumRengi(),
            'aciklama' => $this->record->durumAciklamasi(),
        ];
    }

    public function bekleyenMi(): bool
    {
        return in_array($this->record->durum, BasvuruDurumu::kuyruk(), true);
    }
}
