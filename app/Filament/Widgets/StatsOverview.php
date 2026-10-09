<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\ReportService;
use App\Support\Sar;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Admin and staff only. Riders see no sales numbers. */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    protected function getStats(): array
    {
        $t = app(ReportService::class)->today();

        return [
            Stat::make("Today's sales", Sar::format($t['sales']))
                ->description('Cancelled orders not counted'),
            Stat::make('Orders today', (string) $t['orders']),
            Stat::make('Waiting to confirm', (string) $t['waiting'])
                ->color($t['waiting'] > 0 ? 'warning' : 'success'),
            Stat::make('Opted-in customers', (string) $t['opted_in']),
            Stat::make('Payments to verify', (string) $t['to_verify'])
                ->color($t['to_verify'] > 0 ? 'warning' : 'success'),
        ];
    }
}