<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return []; // no Create: see step 8
    }

    public function getTabs(): array
    {
        $count = fn (string ...$statuses) => OrderResource::getEloquentQuery()
            ->whereIn('status', $statuses)->count();

        return [
            'all' => Tab::make('All'),
            'new' => Tab::make('New')
                ->badge($count('new'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'new')),
            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed')),
            'on_the_way' => Tab::make('On the way')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'out_for_delivery')),
            'delivered' => Tab::make('Delivered')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered')),
            'problem' => Tab::make('No answer / cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['no_answer', 'cancelled'])),
        ];
    }
}