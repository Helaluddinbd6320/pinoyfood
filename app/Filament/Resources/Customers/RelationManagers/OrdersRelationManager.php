<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Order history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_no')
            ->columns([
                TextColumn::make('order_no')->label('#'),
                TextColumn::make('created_at')->dateTime('d M Y, H:i'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => OrdersTable::statusLabel($state)),
                TextColumn::make('total')->money('SAR'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')->label('Open')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}