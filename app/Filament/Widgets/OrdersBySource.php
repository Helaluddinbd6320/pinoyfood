<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderSource;
use App\Services\ReportService;
use Filament\Widgets\Widget;

class OrdersBySource extends Widget
{
    protected static ?int $sort = 3;

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
            'heading' => 'Orders by source, last 7 days',
            'empty' => 'No orders yet.',
            'rows' => $report->bySource($from, $to)
                ->map(fn ($r) => [
                    'label' => OrderSource::tryFrom($r->source)?->label() ?? ucfirst($r->source),
                    'value' => $r->orders.' '.($r->orders === 1 ? 'order' : 'orders').' · SAR '.$r->sales,
                    'color' => 'gray',
                ])
                ->all(),
        ];
    }
}