<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\KurumPanelProvider;
use App\Providers\Filament\UyePanelProvider;
use App\Providers\Filament\YonetimPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    KurumPanelProvider::class,
    UyePanelProvider::class,
    YonetimPanelProvider::class,
    HorizonServiceProvider::class,
];
