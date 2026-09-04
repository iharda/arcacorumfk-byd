<?php

namespace App\Policies;

use App\Models\User;

/**
 * Kullanıcı ve rol yönetimi -- Düzeltme listesi md.6.
 *
 * 💀 `kullanici.yonet` yetkisi tanımlıydı ama KODDA HİÇ KONTROL EDİLMİYORDU:
 * yeni yetkili eklemek, ayrılanın hesabını kapatmak, rol değiştirmek ve
 * telefonunu kaybeden yetkilinin 2FA'sını sıfırlamak yalnızca tinker/SQL ile
 * yapılabiliyordu. Devirden sonra müşteri bunların hiçbirini yapamazdı.
 *
 * 🔒 KİLİTLENME KORUMASI: kimse kendi hesabını pasife alamaz, kendi rolünü
 * değiştiremez, kendi 2FA'sını buradan sıfırlayamaz. Tek yöneticinin kendini
 * dışarıda bırakması geri dönüşü olmayan bir hata olurdu.
 */
class UserPolicy
{
    public function viewAny(User $kullanici): bool
    {
        return $kullanici->can('kullanici.yonet');
    }

    /**
     * Kişinin künyesini görebilir mi? -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 🔑 GÖRME ile YÖNETME ayrıldı. `kullanici.yonet` bilerek yalnızca
     * super'de (hesap açma, rol değiştirme, 2FA sıfırlama). Ama kulüp
     * yetkilisi bir kurumu incelerken çalışanına tıklayıp "bu kim, kartı ne
     * durumda" diye bakabilmeli -- o bilgiyi zaten başvuru inceleme
     * ekranında görüyor.
     *
     * ⚠️ ROL kontrolü, YETKİ kontrolü DEĞİL: `basvuru.gor` yetkisi KURUM
     * rolünde de var (kendi çalışanlarını görsün diye). Ona bağlasaydık kurum
     * hesabı sistemdeki HERKESİN künyesini açabilirdi. KurumPolicy::view
     * aynı sebeple aynı kalıbı kullanıyor.
     *
     * Değiştirme yolları kapalı kalır: `update` ve `rolYonet` hâlâ
     * `kullanici.yonet` istiyor; liste ve menü de super'e özel.
     */
    public function view(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet')
            || $kullanici->hasRole(User::ROL_YETKILI);
    }

    public function update(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet');
    }

    /** Rol değişikliği en hassas işlem: kendi rolüne dokunulamaz. */
    public function rolYonet(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet') && ! $kullanici->is($hedef);
    }

    /** Hesabı pasife alma. Kendi hesabı ve son yönetici korunur. */
    public function pasifeAl(User $kullanici, User $hedef): bool
    {
        if (! $kullanici->can('kullanici.yonet') || $kullanici->is($hedef)) {
            return false;
        }

        return ! $hedef->aktif ? false : ! $this->sonYoneticiMi($hedef);
    }

    public function aktifEt(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet') && ! $hedef->aktif;
    }

    /**
     * 2FA sıfırlama. Kendi 2FA'sını buradan sıfırlamak, panele girmiş
     * birinin ikinci adımı kalıcı olarak atlaması demek olurdu.
     */
    public function ikiAdimliSifirla(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet')
            && ! $kullanici->is($hedef)
            && $hedef->iki_adimli_gizli !== null;
    }

    public function sifreSifirla(User $kullanici, User $hedef): bool
    {
        return $kullanici->can('kullanici.yonet');
    }

    public function create(User $kullanici): bool
    {
        return $kullanici->can('kullanici.yonet');
    }

    /**
     * 🔒 Silme YOK: denetim kayıtları ve akreditasyonlar hesaba bağlı.
     * Ayrılan kişi pasife alınır, hesabı durur.
     */
    public function delete(User $kullanici, User $hedef): bool
    {
        return false;
    }

    /** Panele girebilen SON kişiyi kilitlemeyelim. */
    private function sonYoneticiMi(User $hedef): bool
    {
        if (! $hedef->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return false;
        }

        return User::query()
            ->where('aktif', true)
            ->whereKeyNot($hedef->getKey())
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [User::ROL_SUPER, User::ROL_YETKILI]))
            ->doesntExist();
    }
}
