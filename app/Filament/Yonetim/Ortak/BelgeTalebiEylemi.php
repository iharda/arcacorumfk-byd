<?php

namespace App\Filament\Yonetim\Ortak;

use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Servisler\BasvuruAkisi;
use App\Support\DuzeltmeAlanlari;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Throwable;

/**
 * "Belge iste" -- KARARA BAĞLANMIŞ bir kayıttan, akreditasyona dokunmadan.
 *
 * 💀 Bu düğme eskiden düğme bile değildi: akreditasyon detayındaki "Ek evrak
 * talep et" yalnızca inceleme ekranına GİDİYORDU. Orada da "Belge iste" pasif
 * çıkıyor, tooltip "önce Akreditasyonu geri al" diyordu -- o adım kartı geri
 * alınamaz biçimde iptal ediyor. Yetkili tek bir belge için akreditasyonu
 * yakmak zorunda kalıyordu (bkz. BasvuruAkisi::belgeTalepEt).
 *
 * 🔑 İKİ HEDEF, TEK TANIM:
 *   `akreditasyon()` -- kişi tarafı. Karar `Akreditasyon` kaydında yaşıyor.
 *   `kurum()`        -- kuruluş tarafı. Kurumsal onayda akreditasyon kaydı
 *                       DOĞMUYOR, kararı onaylanmış kurumsal başvuru taşıyor
 *                       (Kurum::onayliKurumsalBasvuru). İlk sürümde yalnız
 *                       kişi tarafı yapılmıştı ve kurum aynı çıkmazda kaldı.
 *
 * 🔑 Modal İŞARETLİ ALANLARDAN yalnız EVRAK türlerini açar. Veri alanları
 * (ad, unvan, telefon) bilerek yok: onaylanmış başvurunun kararına dayanak
 * olan veriyi karar sonrası bir düzeltme turuyla oynatmak, kararı sessizce
 * değiştirmek olurdu.
 */
class BelgeTalebiEylemi
{
    /**
     * Kişi tarafı: akreditasyon detay sayfası.
     *
     * @param  Closure(): Akreditasyon  $akreditasyon
     */
    public static function akreditasyon(Closure $akreditasyon, string $ad = 'belgeIste'): Action
    {
        return self::temel(
            ad: $ad,
            basvuru: fn () => $akreditasyon()->basvuru,
            engel: fn () => self::akreditasyonEngeli($akreditasyon()),
            aciklama: 'Kart AKTİF kalır, giriş yetkisi kesilmez. Kişiye yükleme '
                .'bağlantısı gider; belge geldiğinde başvuru yeniden incelemeye AÇILMAZ.',
            basari: 'Belge talebi gönderildi; kart aktif kalmaya devam ediyor.',
        );
    }

    /**
     * Kuruluş tarafı: kurum detay sayfası.
     *
     * @param  Closure(): Kurum  $kurum
     */
    public static function kurum(Closure $kurum, string $ad = 'belgeIste'): Action
    {
        return self::temel(
            ad: $ad,
            basvuru: fn () => $kurum()->onayliKurumsalBasvuru(),
            engel: fn () => self::kurumEngeli($kurum()),
            aciklama: 'Kuruluşun akreditasyonu AKREDİTE kalır, çalışanlarının kartları '
                .'etkilenmez. Kuruluşa yükleme bağlantısı gider; belge geldiğinde başvuru '
                .'yeniden incelemeye AÇILMAZ.',
            basari: 'Belge talebi gönderildi; kuruluşun akreditasyonu aynı kalıyor.',
        );
    }

    /**
     * @param  Closure(): ?Basvuru  $basvuru
     * @param  Closure(): ?string  $engel
     */
    private static function temel(
        string $ad,
        Closure $basvuru,
        Closure $engel,
        string $aciklama,
        string $basari,
    ): Action {
        return Action::make($ad)
            ->label('Belge iste')
            ->icon('heroicon-m-document-plus')
            ->color('warning')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading('Belge isteyin')
            ->modalDescription($aciklama)
            ->modalSubmitActionLabel('Talebi gönder')
            ->modalCancelActionLabel('Vazgeç')
            ->visible(fn () => auth()->user()?->can('basvuru.incele') ?? false)
            ->disabled(fn () => $engel() !== null)
            ->tooltip(fn () => $engel())
            ->schema([
                TalepAlanlari::kalemler(
                    fn () => ($kayit = $basvuru()) ? DuzeltmeAlanlari::evrakAlanlari($kayit) : [],
                ),

                TalepAlanlari::ekTalep(),

                /*
                 * Süre BİLGİ AMAÇLI. Sürenin sonunda sistem kartı askıya
                 * almaz, talebi kapatmaz: kayıt panoda "süresi geçti" diye
                 * görünür ve kararı yetkili verir (Cüneyt Bey, 05.09.2026).
                 */
                TextInput::make('sure_gun')
                    ->label('Yanıt süresi (gün)')
                    ->helperText('Karşı tarafa "bu tarihe kadar gönderin" diye yazılır. Süre '
                        .'dolduğunda akreditasyon otomatik olarak DÜŞMEZ; kayıt panoda önünüze düşer.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(180)
                    ->default(BasvuruAkisi::BELGE_TALEBI_GUN)
                    ->required(),

                Textarea::make('mesaj')
                    ->label('Ek not (isteğe bağlı)')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, Action $action) use ($basvuru, $engel, $basari) {
                /*
                 * 🔑 Önce ORTAK kalem kontrolü, sonra servis. `halt()` modalı
                 * AÇIK bırakır: hata bildirimle verilip modal kapansaydı
                 * yetkilinin yazdığı başlık, açıklama ve süre uçardı.
                 */
                if ($hata = TalepAlanlari::kalemHatasi($data['notlar'] ?? null, $data['ek_talepler'] ?? null)) {
                    Notification::make()->title($hata)->danger()->send();

                    $action->halt();
                }

                // Görünürlük yetmez: eylem adresi doğrudan da çağrılabilir.
                if ($sebep = $engel()) {
                    Notification::make()->title($sebep)->danger()->send();

                    $action->halt();
                }

                try {
                    app(BasvuruAkisi::class)->belgeTalepEt(
                        $basvuru(),
                        TalepAlanlari::kalemleriTopla($data['notlar'] ?? null),
                        $data['mesaj'] ?? null,
                        TalepAlanlari::ekTalepleriTopla($data['ek_talepler'] ?? null),
                        (int) ($data['sure_gun'] ?? BasvuruAkisi::BELGE_TALEBI_GUN),
                    );
                } catch (Throwable $e) {
                    // Servis hatası bildirim olarak dönsün, 500 olmasın.
                    Notification::make()->title($e->getMessage())->danger()->send();

                    $action->halt();
                }

                Notification::make()->title($basari)->success()->send();
            });
    }

    /**
     * Kişi tarafında uygulanamıyorsa SEBEBİ -- düğme ekrandan kalkmaz,
     * pasifleşir ve neden yapılamadığını söyler (Inceleme::pasifSebebi kalıbı).
     */
    public static function akreditasyonEngeli(Akreditasyon $akreditasyon): ?string
    {
        if ($akreditasyon->basvuru === null) {
            return 'Bu akreditasyona bağlı başvuru kaydı yok; belge talebi başvuru dosyasına yazılır.';
        }

        if (! (auth()->user()?->can('belgeIste', $akreditasyon) ?? false)) {
            return 'Belge talebi yalnızca kartı AKTİF ve başvurusu onaylanmış akreditasyonda açılır.';
        }

        return self::acikTalepEngeli($akreditasyon->basvuru);
    }

    /** Kuruluş tarafındaki karşılığı. */
    public static function kurumEngeli(Kurum $kurum): ?string
    {
        if (! (auth()->user()?->can('belgeIste', $kurum) ?? false)) {
            return 'Belge talebi yalnızca AKREDİTE kuruluşta açılır.';
        }

        $basvuru = $kurum->onayliKurumsalBasvuru();

        if ($basvuru === null) {
            return 'Bu kuruluşun onaylanmış kurumsal başvurusu yok; belge talebi başvuru '
                .'dosyasına yazılır.';
        }

        return self::acikTalepEngeli($basvuru);
    }

    /**
     * 🪤 TEK AÇIK TUR. `duzeltilebilirAlanlar()` ve düzeltme formu
     * `basvurular.duzeltme_notlari` tek alanına bakıyor; ikinci bir tur
     * açılsaydı birincinin istediği kalemler sessizce silinirdi.
     */
    private static function acikTalepEngeli(Basvuru $basvuru): ?string
    {
        return $basvuru->acikDuzeltme() === null
            ? null
            : 'Bu başvuruda yanıtlanmamış bir talep zaten var; önce o kapansın.';
    }
}
