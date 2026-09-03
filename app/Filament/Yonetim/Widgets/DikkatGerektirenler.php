<?php

namespace App\Filament\Yonetim\Widgets;

use App\Enums\AkreditasyonDurumu;
use App\Enums\DegerlendirmePuani;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Degerlendirme;
use App\Models\Kurum;
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
            ->concat(self::suresiBitecekler())
            ->concat(self::dusukDegerlendirmeler())
            ->concat(self::kartiUretilemeyenler());
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
                'ayrinti' => $b->basvuru_no.' · '
                    .(int) $b->gonderildi_at->copy()->startOfDay()->diffInDays(now()->startOfDay()).' gündür kuyrukta',
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

    /** @return Collection<int, array<string, mixed>> */
    public function getSatirlarProperty(): Collection
    {
        return static::satirlar();
    }
}
