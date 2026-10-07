<?php

use App\Planner\PlannerServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\ClubPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;

return [
    AppServiceProvider::class,
    PlatformPanelProvider::class,
    ClubPanelProvider::class,
    PlannerServiceProvider::class,
];
