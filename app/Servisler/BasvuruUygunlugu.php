<?php

namespace App\Servisler;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Yeniden başvuru hakkı -- kim tekrar başvurabilir?
 *
 * 💀 Eskiden e-posta `users` tablosunda koşulsuz benzersizdi: form
 * "daha önce bir hesap açılmış, giriş yapabilirsiniz" deyip duruyordu.
 * Reddedilen aday da, kurumundan ayrılıp başkasına geçen basın mensubu da
 * sisteme BİR DAHA giremiyordu. Üstelik ayrılan kişi `canAccessPanel()`
 * yüzünden giriş de yapamadığı için o mesaj çıkmaz sokaktı.
 *
 * 🔑 Kural: hesap TEKRAR KULLANILIR — yeni başvuru aynı hesaba bağlanır,
 * ikinci bir kullanıcı açılmaz. Engel yalnızca "zaten süren bir işi var"
 * hâllerinde vardır; geçmişte reddedilmiş / ayrılmış / iptal edilmiş olmak
 * engel DEĞİLDİR.
 */
class BasvuruUygunlugu
{
    /**
     * Devam ediyor sayılan başvuru durumları — bunlar varken yenisi alınmaz.
     *
     * 🪤 `Taslak` LİSTEDE DEĞİL: başvuru artık tek adımda gönderiliyor
     * (Revizyon md.1), taslak yalnızca eski akıştan kalan kayıtlarda var.
     * Listede bıraksaydık o kişiler ne taslağı gönderebilir ne de yeniden
     * başvurabilirdi — kapalı bir kapı.
     */
    public const SUREN_DURUMLAR = [
        BasvuruDurumu::Gonderildi,
        BasvuruDurumu::Incelemede,
        BasvuruDurumu::EksikEvrak,
    ];

    /** Süren başvuru mesajı: hesap olsa da olmasa da aynı. */
    private const SUREN_BASVURU_MESAJI = 'Bu e-posta ile devam eden bir başvurunuz var. Sonuç e-posta ile bildirilecektir.';

    /** Turnikede hâlâ bir karşılığı olan akreditasyonlar. */
    public const CANLI_AKREDITASYONLAR = [
        AkreditasyonDurumu::Aktif,
        AkreditasyonDurumu::Askida,
    ];

    /**
     * E-postaya bağlı hesap. Silinmiş hesaplar da aranır: e-posta sütunu
     * veritabanı seviyesinde benzersiz, yumuşak silinen kayıt da yeri tutar.
     */
    public function hesapBul(string $eposta): ?User
    {
        return User::withTrashed()->where('email', $eposta)->first();
    }

    /**
     * Yeni başvuru alınabilir mi? Alınamıyorsa başvurana gösterilecek SEBEP,
     * alınabiliyorsa null döner.
     */
    public function engel(?User $kullanici): ?string
    {
        if ($kullanici === null) {
            return null;   // hiç hesabı yok: normal ilk başvuru
        }

        if ($kullanici->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return 'Bu e-posta bir kulüp hesabına ait. Başvuru için farklı bir adres kullanın.';
        }

        if ($kullanici->basvurular()->whereIn('durum', self::durumDegerleri())->exists()) {
            return self::SUREN_BASVURU_MESAJI;
        }

        if ($kullanici->akreditasyonlar()->whereIn('durum', self::akreditasyonDegerleri())->exists()) {
            return 'Bu e-posta ile geçerli bir akreditasyonunuz var; yeniden başvurmanıza gerek yok.';
        }

        if ($kullanici->hasRole(User::ROL_KURUM) && $kullanici->kurum?->akrediteMi()) {
            return 'Kurumunuz zaten akredite. Çalışan başvuruları kurum panelinden yürütülür.';
        }

        return $this->beklemeEngeli($kullanici);
    }

    /**
     * E-posta adresi için engel. Hesap ONAY anında açıldığından (Revizyon
     * md.3.2) engel iki yerde olabilir: hesabın kendisinde ya da henüz hesabı
     * olmayan, süren bir başvuruda. İkincisi olmadan aynı adresle sınırsız
     * başvuru gönderilebilirdi.
     */
    public function epostaIcinEngel(string $eposta): ?string
    {
        if ($engel = $this->engel($this->hesapBul($eposta))) {
            return $engel;
        }

        $hesapsizSuren = Basvuru::query()
            ->whereNull('kullanici_id')
            ->where('basvuran_eposta', $eposta)
            ->whereIn('durum', self::durumDegerleri())
            ->exists();

        return $hesapsizSuren ? self::SUREN_BASVURU_MESAJI : null;
    }

    /** Akış için: uygun değilse durdurur (ekranlar `epostaIcinEngel` kullanır). */
    public function epostaIcinDogrula(string $eposta): void
    {
        if ($engel = $this->epostaIcinEngel($eposta)) {
            throw new RuntimeException($engel);
        }
    }

    /**
     * Form doğrulama kuralı. `Rule::unique('users','email')` YERİNE kullanılır:
     * kayıtlı e-posta başlı başına engel değildir, engel olan durumlar burada.
     */
    public static function kural(): Closure
    {
        return function (string $alan, mixed $deger, Closure $hata): void {
            if ($engel = app(self::class)->epostaIcinEngel((string) $deger)) {
                $hata($engel);
            }
        };
    }

    /**
     * Reddedilen aday hemen tekrar başvurabilir mi? Varsayılan EVET
     * (`yeniden_basvuru_bekleme_gun` = 0). Kulüp kuyruğu şişerse ayardan gün
     * verilir; süre son RED kararından işler.
     */
    private function beklemeEngeli(User $kullanici): ?string
    {
        $gun = (int) Ayar::al('yeniden_basvuru_bekleme_gun', 0);

        if ($gun <= 0) {
            return null;
        }

        $sonRed = $kullanici->basvurular()
            ->where('durum', BasvuruDurumu::Reddedildi->value)
            ->whereNotNull('karar_at')
            ->max('karar_at');

        if ($sonRed === null) {
            return null;
        }

        $uygunTarih = Carbon::parse($sonRed)->addDays($gun);

        if ($uygunTarih->isFuture()) {
            return 'Başvurunuz '.$uygunTarih->timezone('Europe/Istanbul')->format('d.m.Y')
                .' tarihinden sonra yeniden değerlendirilebilir.';
        }

        return null;
    }

    /** @return array<int, string> */
    private static function durumDegerleri(): array
    {
        return array_column(self::SUREN_DURUMLAR, 'value');
    }

    /** @return array<int, string> */
    private static function akreditasyonDegerleri(): array
    {
        return array_column(self::CANLI_AKREDITASYONLAR, 'value');
    }
}
