<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug'),
                ToggleColumn::make('is_active'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                // products.category_id is restrictOnDelete, so only empty categories can be deleted
                DeleteAction::make()->visible(
                    fn (Category $record) => ! Product::withTrashed()->where('category_id', $record->id)->exists()
                ),
            ]);
    }
}