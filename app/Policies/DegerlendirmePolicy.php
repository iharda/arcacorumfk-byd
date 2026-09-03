<?php

namespace App\Policies;

use App\Models\Degerlendirme;
use App\Models\User;

/**
 * Değerlendirme YALNIZCA kulüp tarafındadır -- briefi md.1 "Görünürlük".
 *
 * 🔒 Blade'de `@can(...)` sarmalı YETMEZ: veriyi ekrana getiren sorgu da
 * yalnızca yönetim panelinde olmalı. Kurum paneli, üye paneli, kapı API'si
 * ve kart bu veriyi hiç taşımaz.
 */
class DegerlendirmePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('degerlendirme.yonet');
    }

    public function view(User $user, Degerlendirme $degerlendirme): bool
    {
        return $user->can('degerlendirme.yonet');
    }

    public function create(User $user): bool
    {
        return $user->can('degerlendirme.yonet');
    }

    public function update(User $user, Degerlendirme $degerlendirme): bool
    {
        return $user->can('degerlendirme.yonet');
    }

    public function delete(User $user, Degerlendirme $degerlendirme): bool
    {
        // Puan SİLİNMEZ, güncellenir: geçmiş denetim kaydında durur.
        return false;
    }
}
