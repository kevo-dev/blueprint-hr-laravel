<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\HrStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    public function getWidgets(): array
    {
        return [
            HrStatsOverview::class,
        ];
    }
}
