<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Duyuru / antrenman / bülten -- Plan v1.0 md.8.
 * Yönetim tarafı 'icerik.yonet' ister; akredite kullanıcı YALNIZCA yayındaki
 * içeriği görür (o kontrol panel sayfalarında, sorgu seviyesinde).
 */
class IcerikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('icerik.yonet');
    }

    public function view(User $user, Model $icerik): bool
    {
        return $user->can('icerik.yonet');
    }

    public function create(User $user): bool
    {
        return $user->can('icerik.yonet');
    }

    public function update(User $user, Model $icerik): bool
    {
        return $user->can('icerik.yonet');
    }

    public function delete(User $user, Model $icerik): bool
    {
        return $user->can('icerik.yonet');
    }
}
