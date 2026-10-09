<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\Widget;

class BestSellers extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.simple-list';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = app(ReportService::class);
        [$from, $to] = $report->range(now()->subDays(6)->toDateString(), now()->toDateString());

        return [
            'heading' => 'Best sellers, last 7 days',
            'empty' => 'No sales yet.',
            'rows' => $report->topProducts($from, $to, 5)
                ->map(fn ($r) => ['label' => $r->name, 'value' => $r->qty.' sold', 'color' => 'primary'])
                ->all(),
        ];
    }
}