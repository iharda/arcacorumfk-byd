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

    public function delete(User $user, Kurum $kurum): bool
    {
        return false;
    }
}
