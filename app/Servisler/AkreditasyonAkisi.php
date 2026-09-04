<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruTuru;
use App\Jobs\KartUret;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\User;
use App\Notifications\AkreditasyonDurumuDegisti;
use App\Support\Sezon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Akreditasyon yaşam döngüsü -- Plan v1.0 md.4, md.5.4.
 *
 * 🔑 Yetki bilgisi kartta değil BURADA. İptal/askı anında turnikede etkilidir.
 * ⏳ Kart PDF/QR üretimi 04. aşamada; bu servis akreditasyon KAYDINI yönetir.
 */
class AkreditasyonAkisi
{
    public function __construct(
        private DenetimYazici $denetim,
        private KartNoUretici $kartNo,
    ) {}

    /** Onaylanan bireysel başvurudan akreditasyon doğar. */
    public function basvurudanOlustur(Basvuru $basvuru): ?Akreditasyon
    {
        if ($basvuru->tur === BasvuruTuru::Kurum) {
            return null;   // Kurumsal onay = kurumun akredite olması, kart çıkmaz.
        }

        if ($basvuru->akreditasyon()->exists()) {
            return $basvuru->akreditasyon;   // yeniden onay: mükerrer kart üretme
        }

        return DB::transaction(function () use ($basvuru) {
            $akreditasyon = $this->kartNo->uret(
                $basvuru->tur,
                fn (array $numara) => Akreditasyon::create($numara + [
                    'kullanici_id' => $basvuru->kullanici_id,
                    'basvuru_id' => $basvuru->id,
                    'kurum_id' => $basvuru->kurum_id,
                    'durum' => AkreditasyonDurumu::Aktif,
                    /*
                     * 🔒 Bölge yetkileri onay anında atanır (Düzeltme
                     * listesi md.9). Boş bırakılırsa `gecerliMi()` HER
                     * BÖLGEYE izin verir; kısıtlı alanı olan kulüp
                     * varsayılanı Ayarlar'dan doldurur.
                     */
                    'bolge_yetkileri' => (array) Ayar::al('varsayilan_bolgeler', []) ?: null,
                    /*
                     * 💀 SEZON ve GEÇERLİLİK (M9 №2). Bu alanlar "Faz 2" diye
                     * boş bırakılmıştı ve `gecerliMi()` boş bitiş tarihini
                     * "süresiz geçerli" sayıyor: üretilen HER kart süresizdi,
                     * geçen sezonun kartı bu sezon da turnikeden geçerdi.
                     *
                     * Sezon tanımlı DEĞİLSE alanlar yine boş kalır -- yarım
                     * yapılandırma yüzünden kart üretimini durdurmak kulübü
                     * maç günü kilitlerdi. Eksiklik panoda uyarı olarak çıkar.
                     */
                ] + Sezon::alanlar()),
            );

            // 🪤 syncRoles DEĞİL: kişi aynı zamanda kurum yetkilisi olabilir,
            // syncRoles o rolü de siler ve kurum panelinden atardı. Yalnızca
            // diğer bireysel tür rolü kaldırılır.
            [$rol, $eskiRol] = $basvuru->tur === BasvuruTuru::BasinMensubu
                ? [User::ROL_BASIN, User::ROL_ICERIK]
                : [User::ROL_ICERIK, User::ROL_BASIN];

            $basvuru->kullanici->removeRole($eskiRol);
            $basvuru->kullanici->assignRole($rol);

            $this->denetim->yaz('akreditasyon.olusturuldu', $akreditasyon, yeni: [
                'kart_no' => $akreditasyon->kart_no,
            ]);

            // Kart üretimi kuyrukta: başsız Chrome birkaç saniye sürüyor,
            // yetkili onay düğmesine basınca ekran beklemesin.
            // afterCommit: iş, kayıt gerçekten yazılmadan başlamasın.
            // 🔑 `Auth::id()` DISPATCH ANINDA okunur; işçide oturum yok.
            KartUret::dispatch($akreditasyon, tetikleyenId: Auth::id())->afterCommit();

            return $akreditasyon;
        });
    }

    /**
     * Kurumdan ayrılış -- md.5.4. Ayrılış işareti ile akreditasyon iptali TEK
     * işlemde yürür.
     *
     * 💀 Eskiden ikisi ayrı ayrı yazılıyordu: iptal patlarsa kişi "ayrıldı"
     * görünüp turnikeden GEÇMEYE DEVAM EDİYORDU (Yusuf/IT, 2026-08-23).
     * 🪤 Ayrıca yalnızca `akreditasyon` ilişkisi iptal ediliyordu; o ilişki
     * latestOfMany, yani EN YENİ kaydı verir. Yeniden başvuran birinin eski
     * kaydı aktif kalabiliyordu — burada hepsi kapatılır.
     */
    public function kullaniciAyrildi(User $kullanici, ?string $not = null): void
    {
        if ($kullanici->ayrildi_at !== null) {
            throw new RuntimeException('Bu kişi zaten ayrılmış görünüyor.');
        }

        DB::transaction(function () use ($kullanici, $not) {
            $ayrilis = now();

            $kullanici->forceFill(['ayrildi_at' => $ayrilis, 'aktif' => false])->save();

            $this->denetim->yaz('calisan.ayrilis_bildirildi', $kullanici,
                yeni: ['ayrildi_at' => $ayrilis->toIso8601String()], not: $not);

            $neden = 'Kurumdan ayrılış bildirimi'.($not ? ' — '.$not : '');

            $kullanici->akreditasyonlar()
                ->where('durum', '!=', AkreditasyonDurumu::Iptal->value)
                ->get()
                ->each(fn (Akreditasyon $akreditasyon) => $this->iptalEt($akreditasyon, $neden));
        });
    }

    /**
     * Sezon ve geçerlilik tarihlerini yazar -- M9 №2 / M8 №8.
     *
     * 🔑 Ayrı bir metot çünkü DURUM DEĞİŞTİRMİYOR: kart aktif kalır, yalnızca
     * ne zamana kadar geçerli olduğu belirlenir. `durumaGec()` kullanılsaydı
     * kişiye gereksiz bir "akreditasyon durumunuz değişti" bildirimi giderdi.
     *
     * ⚠️ Geçmişe bir bitiş tarihi yazmak kartı ANINDA geçersizleştirir
     * (`gecerliMi()` bugüne bakar) -- turnikede karşılığı olan bir işlem, bu
     * yüzden denetime yazılıyor.
     */
    public function gecerliligiYaz(
        Akreditasyon $akreditasyon,
        ?string $sezon,
        ?string $baslangic,
        ?string $bitis,
    ): void {
        $eski = [
            'sezon' => $akreditasyon->sezon,
            'gecerlilik_baslangic' => $akreditasyon->gecerlilik_baslangic?->toDateString(),
            'gecerlilik_bitis' => $akreditasyon->gecerlilik_bitis?->toDateString(),
        ];

        $yeni = [
            'sezon' => filled($sezon) ? $sezon : null,
            'gecerlilik_baslangic' => filled($baslangic) ? $baslangic : null,
            'gecerlilik_bitis' => filled($bitis) ? $bitis : null,
        ];

        if ($eski === $yeni) {
            return;   // gerçek bir değişiklik yoksa denetimde gürültü olmasın
        }

        $akreditasyon->fill($yeni)->save();

        $this->denetim->yaz('akreditasyon.gecerlilik_degisti', $akreditasyon,
            eski: $eski, yeni: $yeni);
    }

    public function askiyaAl(Akreditasyon $akreditasyon, string $gerekce): void
    {
        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Askida, 'akreditasyon.askiya_alindi', $gerekce, [
            'askiya_alindi_at' => now(),
        ]);
    }

    public function yenidenAktiflestir(Akreditasyon $akreditasyon): void
    {
        if ($akreditasyon->durum === AkreditasyonDurumu::Iptal) {
            throw new RuntimeException('İptal edilmiş akreditasyon yeniden aktifleştirilemez; yeni başvuru gerekir.');
        }

        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Aktif, 'akreditasyon.yeniden_aktif', null, [
            'askiya_alindi_at' => null,
        ]);
    }

    /**
     * İptal. Ayrılış bildiriminde OTOMATİK çağrılır (md.5.4) — turnike erişimi
     * anında kapanır, kart geri toplanmaz.
     */
    public function iptalEt(Akreditasyon $akreditasyon, string $neden, string $aktorTip = 'kullanici'): void
    {
        if ($akreditasyon->durum === AkreditasyonDurumu::Iptal) {
            return;   // zaten iptal; mükerrer bildirim gönderme
        }

        $this->durumaGec($akreditasyon, AkreditasyonDurumu::Iptal, 'akreditasyon.iptal', $neden, [
            'iptal_at' => now(),
            'iptal_nedeni' => $neden,
        ], $aktorTip);
    }

    private function durumaGec(
        Akreditasyon $akreditasyon,
        AkreditasyonDurumu $hedef,
        string $olay,
        ?string $gerekce,
        array $alanlar = [],
        string $aktorTip = 'kullanici',
    ): void {
        DB::transaction(function () use ($akreditasyon, $hedef, $olay, $gerekce, $alanlar, $aktorTip) {
            $eski = $akreditasyon->durum;

            $akreditasyon->fill($alanlar + [
                'durum' => $hedef,
                'durum_degistiren_id' => Auth::id(),
            ])->save();

            $this->denetim->yaz($olay, $akreditasyon,
                eski: ['durum' => $eski->value],
                yeni: ['durum' => $hedef->value],
                not: $gerekce,
                aktorTip: $aktorTip);
        });

        $akreditasyon->kullanici?->notify(new AkreditasyonDurumuDegisti($akreditasyon, $gerekce));
    }
}
