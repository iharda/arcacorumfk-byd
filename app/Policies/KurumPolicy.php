<?php

namespace App\Policies;

use App\Models\Kurum;
use App\Models\User;

class KurumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kurum.gor');
    }

    public function view(User $user, Kurum $kurum): bool
    {
        if ($user->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return $user->can('kurum.gor');
        }

        return $user->kurum_id === $kurum->id;
    }

    public function create(User $user): bool
    {
        return false;   // Kurum kaydı başvuru formundan doğar.
    }

    public function update(User $user, Kurum $kurum): bool
    {
        return $user->can('kurum.yonet');
    }

    public function akredite(User $user, Kurum $kurum): bool
    {
        return $user->can('kurum.akredite');
    }

    /**
     * Akredite kuruluştan belge isteyebilir mi? -- Test User 2 vakası
     * (05.09.2026).
     *
     * 💀 Belge talebi ilk sürümde YALNIZ KİŞİ tarafına konmuştu; düğme
     * `AkreditasyonDetay`'da yaşıyor ve o sayfa bir akreditasyon kaydı
     * gerektiriyor. Kurumsal onayda böyle bir kayıt doğmuyor, dolayısıyla
     * onaylanmış bir kurum başvurusunda belge istemenin tek yolu yine
     * "Akreditasyonu geri al" kalmıştı -- düzeltmek istediğimiz şeyin ta
     * kendisi, sadece öbür kapıda.
     *
     * 🔑 Kişideki kuralın kurum karşılığı: kart AKTİF yerine kuruluş
     * AKREDİTE, başvuru yine ONAYLANMIŞ (bkz. AkreditasyonPolicy::belgeIste).
     */
    public function belgeIste(User $user, Kurum $kurum): bool
    {
        return $user->can('basvuru.incele') && $kurum->akrediteMi();
    }

    public function delete(User $user, Kurum $kurum): bool
    {
        return false;
    }
}
