<?php

namespace App\Policies;

use App\Enums\BasvuruDurumu;
use App\Models\Basvuru;
use App\Models\User;

/**
 * Başvuru yetkileri -- Plan v1.0 md.11.
 *
 * 🔑 İki katman var ve İKİSİ DE gerekli:
 *   1) yetki  — kullanıcının rolünde bu izin var mı?
 *   2) kapsam — bu KAYIT ona mı ait? (kurum yalnızca kendi çalışanlarını,
 *      başvuran yalnızca kendi başvurusunu görür)
 * ValCert dersi: yetkiye sahip olmak "hepsini gör" demek DEĞİL.
 */
class BasvuruPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('basvuru.gor');
    }

    public function view(User $user, Basvuru $basvuru): bool
    {
        /*
         * 💥 KENDİ başvurusunu görmek için yetki GEREKMEZ.
         * `basvuru.gor` BAŞKASININ başvurusunu görme yetkisidir; basın mensubu
         * ve içerik üreticisi rollerinde yok. Yetki kontrolü en başta durunca
         * başvuran kendi kaydına da giremiyordu — aynı hata akreditasyonda
         * "Kartım" ekranını boş kutuya çevirmişti (kart görseli 403).
         * Kapsam kontrolü aşağıda zaten sahipliği doğruluyor.
         */
        if ($basvuru->kullanici_id === $user->id) {
            return true;
        }

        if (! $user->can('basvuru.gor')) {
            return false;
        }

        return $this->kapsamda($user, $basvuru);
    }

    /** Yetkili incelemeye alabilir mi? */
    public function incele(User $user, Basvuru $basvuru): bool
    {
        return $user->can('basvuru.incele')
            && $basvuru->durum === BasvuruDurumu::Gonderildi;
    }

    public function eksikEvrakIste(User $user, Basvuru $basvuru): bool
    {
        return $user->can('basvuru.incele')
            && $basvuru->durum === BasvuruDurumu::Incelemede;
    }

    public function kararVer(User $user, Basvuru $basvuru): bool
    {
        return $user->can('basvuru.karar')
            && $basvuru->durum === BasvuruDurumu::Incelemede;
    }

    /** Başvuran kendi başvurusunu gönderebilir/düzeltebilir mi? */
    public function gonder(User $user, Basvuru $basvuru): bool
    {
        return $basvuru->kullanici_id === $user->id
            && in_array($basvuru->durum, [BasvuruDurumu::Taslak, BasvuruDurumu::EksikEvrak], true);
    }

    public function create(User $user): bool
    {
        return false;   // Başvuruyu YETKİLİ oluşturmaz; kamuya açık formdan gelir.
    }

    public function update(User $user, Basvuru $basvuru): bool
    {
        return false;   // Değişiklik akış servisinden geçer, serbest düzenleme YOK.
    }

    public function delete(User $user, Basvuru $basvuru): bool
    {
        return false;   // Başvuru silinmez; karar geçmişi korunur.
    }

    private function kapsamda(User $user, Basvuru $basvuru): bool
    {
        // Kulüp tarafı hepsini görür
        if ($user->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return true;
        }

        // Kurum: yalnızca kendi kurumunun başvuruları
        if ($user->hasRole(User::ROL_KURUM)) {
            return $user->kurum_id !== null && $basvuru->kurum_id === $user->kurum_id;
        }

        // Birey: yalnızca kendi başvurusu
        return $basvuru->kullanici_id === $user->id;
    }
}
