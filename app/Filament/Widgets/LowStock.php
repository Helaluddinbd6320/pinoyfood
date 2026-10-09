<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\Widget;

class LowStock extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.simple-list';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Low stock',
            'empty' => 'Nothing is running low.',
            'note' => 'Items at 0 show "Sold out" on the website automatically.',
            'rows' => app(ReportService::class)->lowStock(8)
                ->map(fn ($p) => [
                    'label' => $p->name,
                    'value' => (int) $p->stock === 0 ? 'Sold out' : $p->stock.' left',
                    'color' => (int) $p->stock <= 3 ? 'danger' : 'warning',
                ])
                ->all(),
        ];
    }
}