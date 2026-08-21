<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Sayisal id ic tarafta (join'ler ucuz kalir), ULID disariya acilan kimlik.
 * Plan v1.0 md.11: "Tum ID'ler tahmin edilemez" -- rota baglamasi ulid uzerinden
 * yapilir, sirali id HICBIR adreste gorunmez.
 */
trait UlidAnahtari
{
    protected static function bootUlidAnahtari(): void
    {
        static::creating(function ($model) {
            if (blank($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
