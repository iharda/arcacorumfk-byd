<?php

namespace App\Policies;

use App\Models\Akreditasyon;
use App\Models\User;

class AkreditasyonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('akreditasyon.gor');
    }

    public function view(User $user, Akreditasyon $akreditasyon): bool
    {
        if (! $user->can('akreditasyon.gor')) {
            return false;
        }

        if ($user->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return true;
        }

        // Kurum yalnızca kendi çalışanlarını, birey yalnızca kendini görür.
        return $user->hasRole(User::ROL_KURUM)
            ? $akreditasyon->kurum_id === $user->kurum_id
            : $akreditasyon->kullanici_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;   // Onaylanan başvurudan doğar.
    }

    public function update(User $user, Akreditasyon $akreditasyon): bool
    {
        return false;   // Değişiklik AkreditasyonAkisi üzerinden.
    }

    public function delete(User $user, Akreditasyon $akreditasyon): bool
    {
        return false;   // Silinmez; iptal edilir, geçmiş korunur.
    }
}
