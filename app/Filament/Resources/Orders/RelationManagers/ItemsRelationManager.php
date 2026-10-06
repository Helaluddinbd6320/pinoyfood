<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    // Items are snapshots. Changing them needs the server-side recompute (steps 7 and 8).
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')->label('Product'),
                TextColumn::make('qty'),
                TextColumn::make('list_price')->label('Normal price')->money('SAR'),
                TextColumn::make('unit_price')->label('Charged')->money('SAR'),
                TextColumn::make('line_total')->label('Line total')->money('SAR'),
            ])
            ->paginated(false);
    }
}