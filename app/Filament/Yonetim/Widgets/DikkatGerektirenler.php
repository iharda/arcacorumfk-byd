<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\DegerlendirmePuani;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\KapiIstemcisi;
use App\Models\Kurum;
use App\Support\Sezon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * "Elini değdirmen gereken" satırlar tek listede -- briefi md. B.3, Widget D.
 *
 * Her satır bir SEBEP etiketi ve doğrudan bağlantı taşır. En değerlisi son
 * madde: kart üretimi kuyrukta patlarsa bugün bunu kimse görmüyor.
 *
 * 🔒 Düşük değerlendirme satırı `degerlendirme.yonet` yetkisi olmadan HİÇ
 * sorgulanmaz -- yetkisiz yetkilinin ekranına puan sızmasın.
 */
class DikkatGerektirenler extends Widget
{
    protected string $view = 'filament.yonetim.widgets.dikkat-gerektirenler';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /** Sebep başına en fazla bu kadar satır; gerisi sayı olarak yazılır. */
    private const SEBEP_BASINA = 5;

    private static ?Collection $onbellek = null;

    public static function canView(): bool
    {
        return (auth()->user()?->can('basvuru.gor') ?? false) && static::satirlar()->isNotEmpty();
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function satirlar(): Collection
    {
        return self::$onbellek ??= collect()
            ->concat(self::uzunBekleyenler())
            ->concat(self::kontenjaniDolanlar())
            ->concat(self::suresizKartlar())
            ->concat(self::suresiBitecekler())
            ->concat(self::eksikEvrakiGecikenler())
            ->concat(self::biletiDolanlar())
            ->concat(self::dusukDegerlendirmeler())
            ->concat(self::kartiUretilemeyenler())
            ->concat(self::kurulmamisKapilar());
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function uzunBekleyenler(): Collection
    {
        $satirlar = Basvuru::query()
            ->kuyrukta()
            ->where('gonderildi_at', '<', now()->copy()->subDays(7))
            ->orderBy('gonderildi_at')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Basvuru $b) => [
                'sebep' => 'Kuyrukta uzun bekleyen',
                'renk' => 'danger',
                'baslik' => $b->kurum->resmi_unvan ?? $b->basvuranAdi(),
                // Süre cümlesi kuyruk listesiyle AYNI kaynaktan (T4).
                'ayrinti' => implode(' · ', array_filter([$b->basvuru_no, $b->bekleyenSure()])),
                'adres' => route('filament.yonetim.resources.basvurular.inceleme', ['record' => $b->ulid]),
            ]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function kontenjaniDolanlar(): Collection
    {
        $satirlar = Kurum::query()
            ->whereNotNull('kontenjan')
            ->where('akreditasyon_durumu', 'akredite')
            ->withCount(['akreditasyonlar as aktif_kart' => fn ($query) => $query->where('durum', AkreditasyonDurumu::Aktif->value)])
            ->get()
            ->filter(fn (Kurum $k) => $k->aktif_kart >= $k->kontenjan)
            ->take(self::SEBEP_BASINA)
            ->map(fn (Kurum $k) => [
                'sebep' => 'Kontenjanı dolan kurum',
                'renk' => 'warning',
                'baslik' => $k->resmi_unvan,
                'ayrinti' => $k->aktif_kart.' / '.$k->kontenjan.' kart kullanılmış',
                'adres' => route('filament.yonetim.resources.kurumlar.index'),
            ])
            ->values();

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * 💀 EKSİK EVRAK İSTENİP UNUTULAN BAŞVURU.
     *
     * `biletiDolanlar()` yalnızca bağlantının SÜRESİ DOLMUŞ olanları yakalıyor.
     * Bağlantı hâlâ geçerliyken haftalarca hiçbir şey yüklemeyen başvuru ise
     * hiçbir listede yoktu: kulüp "belge bekleniyor" diye bırakıyor, başvuran
     * unutuyor, başvuru sessizce ölüyordu.
     *
     * Eşik Ayarlar'dan gelir; 0 = uyarma.
     *
     * 🪤 Süre TALEP ANINDAN işler, başvurunun gönderiminden değil: ölçtüğümüz
     * şey "ne zamandır belge bekliyoruz".
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function eksikEvrakiGecikenler(): Collection
    {
        $gun = (int) Ayar::al('eksik_evrak_uyari_gun', 7);

        if ($gun <= 0) {
            return collect();
        }

        $sinir = now()->copy()->subDays($gun);

        $satirlar = Basvuru::query()
            ->where('durum', BasvuruDurumu::EksikEvrak->value)
            ->with(['kurum', 'duzeltmeler'])
            // Yanıtlanmamış turun talep tarihi eşiğin gerisinde mi?
            ->whereHas('duzeltmeler', fn ($sorgu) => $sorgu
                ->whereNull('yanit_at')
                ->where('talep_at', '<', $sinir))
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(function (Basvuru $b) {
                $talep = $b->acikDuzeltme()?->talep_at;

                return [
                    'sebep' => 'Eksik evrak gecikti',
                    'renk' => 'warning',
                    'baslik' => $b->kurum->resmi_unvan ?? $b->basvuranAdi(),
                    'ayrinti' => implode(' · ', array_filter([
                        $b->basvuru_no,
                        $talep === null ? null : (int) $talep->diffInDays(now()).' gündür yüklenmedi',
                    ])),
                    'adres' => route('filament.yonetim.resources.basvurular.inceleme', ['record' => $b->ulid]),
                ];
            })
            ->values();

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * 💀 SÜRESİ DOLMUŞ DÜZELTME BAĞLANTISI -- Tutarsızlık incelemesi M9 №7.
     *
     * Eksik evrak istenen başvuru `eksik_evrak` durumunda bekler ve başvuran
     * yalnızca kendisine gönderilen bağlantıyla belge yükleyebilir. Bağlantının
     * süresi dolduğunda başvuran sayfada "kulüple iletişime geçin" mesajını
     * görüyor -- ama kulüp tarafında bunu GÖSTEREN HİÇBİR ŞEY YOKTU.
     * "Bağlantıyı yeniden gönder" eylemi inceleme ekranında duruyor, kimse
     * basmıyor: başvuru ne başvuranın ne yetkilinin elinde, ortada kalıyor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function biletiDolanlar(): Collection
    {
        $satirlar = Basvuru::query()
            ->where('durum', BasvuruDurumu::EksikEvrak->value)
            /*
             * 🪤 "Kullanılabilir bileti olmayan" demek: hiç bileti olmayan da
             * dahil değil -- süresi dolmuş ya da iptal edilmiş olanlar. Açık
             * (kullanılabilir) bileti olan başvuru beklemede sayılmaz.
             */
            ->whereDoesntHave('biletler', fn ($sorgu) => $sorgu
                ->whereNull('kullanildi_at')
                ->whereNull('iptal_at')
                ->where('gecerlilik_bitis', '>', now()))
            ->orderBy('gonderildi_at')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Basvuru $b) => [
                'sebep' => 'Düzeltme bağlantısı geçersiz',
                'renk' => 'danger',
                'baslik' => $b->basvuranAdi(),
                'ayrinti' => implode(' · ', array_filter([
                    $b->basvuru_no,
                    'Başvuran belge yükleyemiyor; bağlantıyı yeniden gönderin',
                ])),
                'adres' => route('filament.yonetim.resources.basvurular.inceleme', ['record' => $b->ulid]),
            ]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * 💀 SÜRESİZ KARTLAR -- Tutarsızlık incelemesi M9 №2.
     *
     * `gecerliMi()` boş `gecerlilik_bitis` değerini "süresiz geçerli" sayar.
     * Sütun hiçbir ekrandan doldurulmadığı için sistemdeki BÜTÜN aktif kartlar
     * süresizdi: sezon bitse de geçen sezonun kartı turnikeden geçerdi. Üstelik
     * aşağıdaki `suresiBitecekler()` kutusu da bu sütuna baktığı için hiç
     * çizilmiyordu -- eksiklik kendini göstermiyordu bile. Artık gösteriyor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function suresizKartlar(): Collection
    {
        $adet = Akreditasyon::query()
            ->where('durum', AkreditasyonDurumu::Aktif->value)
            ->whereNull('gecerlilik_bitis')
            ->count();

        if ($adet === 0) {
            return collect();
        }

        $satirlar = collect([[
            'sebep' => 'Süresiz kart',
            'renk' => 'danger',
            'baslik' => $adet.' aktif kartın bitiş tarihi yok',
            'ayrinti' => Sezon::tanimliMi()
                ? 'Bu kartlar sezon sonunda geçersizleşmez. Akreditasyonlar ekranından seçip "Sezonu uygula" deyin.'
                : 'Bu kartlar sezon sonunda geçersizleşmez. Önce Ayarlar > Sezon bölümünü doldurun.',
            'adres' => route('filament.yonetim.resources.akreditasyonlar.index'),
        ]]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function suresiBitecekler(): Collection
    {
        $satirlar = Akreditasyon::query()
            ->with('kullanici')
            ->where('durum', AkreditasyonDurumu::Aktif->value)
            // 🪤 whereDate() DEĞİL; aralık sorgusu indeksi kullanır.
            ->whereBetween('gecerlilik_bitis', [now()->startOfDay(), now()->copy()->addDays(30)->endOfDay()])
            ->orderBy('gecerlilik_bitis')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Akreditasyon $a) => [
                'sebep' => 'Süresi bitecek akreditasyon',
                'renk' => 'warning',
                'baslik' => $a->kullanici->name ?? $a->kart_no,
                'ayrinti' => $a->kart_no.' · '
                    .$a->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y').' tarihinde doluyor',
                'adres' => route('filament.yonetim.resources.akreditasyonlar.index'),
            ]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * Puanın işe yaradığı yer: liste değil, UYARI. Yalnızca AKTİF akreditasyon
     * sahibi kişiler ve akredite kurumlar -- geçmişteki bir puan bugünkü
     * ekranı doldurmasın.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function dusukDegerlendirmeler(): Collection
    {
        if (! (auth()->user()?->can('degerlendirme.yonet') ?? false)) {
            return collect();
        }

        $dusuk = DegerlendirmePuani::dusuk();

        $kurumlar = Degerlendirme::query()
            ->where('hedef_tip', Degerlendirme::HEDEF_KURUM)
            ->whereIn('puan', $dusuk)
            ->whereHas('kurum', fn ($query) => $query->where('akreditasyon_durumu', 'akredite'))
            ->with('kurum')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Degerlendirme $d) => [
                'sebep' => 'Düşük değerlendirme',
                'renk' => 'danger',
                'baslik' => $d->hedefAdi(),
                'ayrinti' => 'Kurum · '.$d->puan->value.' · '.$d->puan->etiket(),
                'adres' => route('filament.yonetim.resources.kurumlar.index'),
            ]);

        $kisiler = Degerlendirme::query()
            ->where('hedef_tip', Degerlendirme::HEDEF_KISI)
            ->whereIn('puan', $dusuk)
            ->whereNotNull('kullanici_id')
            ->whereHas('kullanici.akreditasyonlar',
                fn ($query) => $query->where('durum', AkreditasyonDurumu::Aktif->value))
            ->with('kullanici')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Degerlendirme $d) => [
                'sebep' => 'Düşük değerlendirme',
                'renk' => 'danger',
                'baslik' => $d->hedefAdi(),
                'ayrinti' => 'Kişi · '.$d->puan->value.' · '.$d->puan->etiket(),
                'adres' => route('filament.yonetim.resources.kullanicilar.index'),
            ]);

        $satirlar = $kurumlar->concat($kisiler);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * 💀 Akreditasyon aktif ama kartı yok ve üzerinden bir saat geçmiş: kart
     * üretim kuyruğu patlamış demektir. Bugün bunu gösteren HİÇBİR ekran yok;
     * kişi kapıya gelene kadar fark edilmiyor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function kartiUretilemeyenler(): Collection
    {
        $satirlar = Akreditasyon::query()
            ->with('kullanici')
            ->where('durum', AkreditasyonDurumu::Aktif->value)
            ->where('created_at', '<', now()->copy()->subHour())
            /*
             * 🪤 `guncelKart` bir `latestOfMany` ilişkisi; `whereDoesntHave`
             * ile birlikte kullanmak belirsiz. Koşul doğrudan `kartlar`
             * üzerinden yazılıyor: arşivlenmemiş HİÇ kartı yok.
             */
            ->whereDoesntHave('kartlar', fn ($query) => $query->where('arsiv', false))
            ->orderBy('created_at')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (Akreditasyon $a) => [
                'sebep' => 'Kartı üretilemeyen',
                'renk' => 'danger',
                'baslik' => $a->kullanici->name ?? $a->kart_no,
                'ayrinti' => $a->kart_no.' · akreditasyon '
                    .$a->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' tarihinde açıldı, kart yok',
                'adres' => route('filament.yonetim.resources.akreditasyonlar.index'),
            ]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /**
     * 💀 Panelde tanimli ama sahada hic okutma yapmamis ETKIN kapi: cihazin
     * anahtari girilmemis demektir. Kart uretilemeyen akreditasyonun kapi
     * karsiligi -- ikisi de "kayit acildi, gerceklesmedi" durumu ve ikisi de
     * bugun ancak mac gunu, kapida fark ediliyor.
     *
     * 🕐 24 saat eşiği: kapi tanimlanip cihaz ertesi gun kuruluyor olabilir,
     * ayni saat icinde alarm vermek gurultu olurdu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function kurulmamisKapilar(): Collection
    {
        // 🔒 Kapıya erişemeyecek yetkiliye açamayacağı bir bağlantı gösterme
        // (düşük değerlendirme satırındaki gerekçenin aynısı).
        if (! (auth()->user()?->can('kapi.yonet') ?? false)) {
            return collect();
        }

        $satirlar = KapiIstemcisi::query()
            ->where('aktif', true)
            ->whereNull('son_kullanim_at')
            ->where('created_at', '<', now()->copy()->subDay())
            ->orderBy('created_at')
            ->limit(self::SEBEP_BASINA)
            ->get()
            ->map(fn (KapiIstemcisi $k) => [
                'sebep' => 'Kurulumu tamamlanmamış kapı',
                'renk' => 'warning',
                'baslik' => $k->ad,
                'ayrinti' => $k->kapi_kodu.' · '
                    .$k->created_at->timezone('Europe/Istanbul')->format('d.m.Y').' tarihinde tanımlandı, hiç okutma yok',
                'adres' => route('filament.yonetim.resources.kapilar.detay', ['record' => $k->ulid]),
            ]);

        /** @var Collection<int, array<string, mixed>> $satirlar */
        return $satirlar;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getSatirlarProperty(): Collection
    {
        return static::satirlar();
    }
}
