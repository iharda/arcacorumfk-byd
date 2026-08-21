<?php

namespace App\Policies;

use App\Models\KapiIstemcisi;
use App\Models\User;

/** Kapı anahtarları en hassas yetki: yalnızca 'kapi.yonet' izni olan görür. */
class KapiIstemcisiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kapi.yonet');
    }

    public function view(User $user, KapiIstemcisi $istemci): bool
    {
        return $user->can('kapi.yonet');
    }

    public function create(User $user): bool
    {
        return $user->can('kapi.yonet');
    }

    public function update(User $user, KapiIstemcisi $istemci): bool
    {
        return $user->can('kapi.yonet');
    }

    public function delete(User $user, KapiIstemcisi $istemci): bool
    {
        return false;   // Silinmez: geçiş kayıtları bu kapıya bağlı. Pasife alın.
    }
}
