<?php

namespace App\Policies;

use App\Models\GecisKaydi;
use App\Models\User;

/** Geçiş kayıtları OKUNUR. Eklenir; değiştirilmez, silinmez (md.10). */
class GecisKaydiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gecis.gor');
    }

    public function view(User $user, GecisKaydi $kayit): bool
    {
        return $user->can('gecis.gor');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GecisKaydi $kayit): bool
    {
        return false;
    }

    public function delete(User $user, GecisKaydi $kayit): bool
    {
        return false;
    }
}
