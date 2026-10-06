<?php

namespace App\Filament\Resources\Areas\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AreasTable
{
    // No delete action: areas are switched off with the toggle, so history stays intact.
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('delivery_fee_override')
                    ->label('Delivery fee')->money('SAR')->placeholder('Global fee'),
                ToggleColumn::make('is_active')->label('We deliver here'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }
}