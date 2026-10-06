<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // same rule as the table: only empty categories can be deleted
            DeleteAction::make()->visible(
                fn (Category $record) => ! Product::withTrashed()->where('category_id', $record->id)->exists()
            ),
        ];
    }
}