<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\OrderSource;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Reports extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    public string $from = '';

    public string $to = '';

    /** Admin only: the report shows margins from cost snapshots. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->from = now()->subDays(29)->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = app(ReportService::class);
        [$from, $to] = $report->range($this->from, $this->to);

        return [
            'rangeLabel' => $from->format('d M Y').' to '.$to->format('d M Y'),
            'summary' => $report->summary($from, $to),
            'sources' => $report->bySource($from, $to)->map(function ($r) {
                $r->label = OrderSource::tryFrom($r->source)?->label() ?? ucfirst($r->source);

                return $r;
            }),
            'areas' => $report->byArea($from, $to),
            'products' => $report->topProducts($from, $to, 10),
        ];
    }
}