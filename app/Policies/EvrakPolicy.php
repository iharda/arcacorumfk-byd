<?php

namespace App\Policies;

use App\Models\Evrak;
use App\Models\User;

/**
 * Evrak görüntüleme. Kimlik görselleri burada — kapsam kontrolü ŞART.
 * Erişimin kendisi de denetim kaydına düşer (md.10: kişisel veri içeren
 * loglara erişim de loglanır).
 */
class EvrakPolicy
{
    public function view(User $user, Evrak $evrak): bool
    {
        return app(BasvuruPolicy::class)->view($user, $evrak->basvuru);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Evrak $evrak): bool
    {
        return false;
    }

    public function delete(User $user, Evrak $evrak): bool
    {
        return false;
    }
}
