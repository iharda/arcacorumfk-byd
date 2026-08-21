<?php

namespace App\Policies;

use App\Models\DenetimKaydi;
use App\Models\User;

/** Denetim kaydı SADECE OKUNUR (md.10). Yazma yolu policy'de de kapalı. */
class DenetimKaydiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('denetim.gor');
    }

    public function view(User $user, DenetimKaydi $kayit): bool
    {
        return $user->can('denetim.gor');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DenetimKaydi $kayit): bool
    {
        return false;
    }

    public function delete(User $user, DenetimKaydi $kayit): bool
    {
        return false;
    }
}
