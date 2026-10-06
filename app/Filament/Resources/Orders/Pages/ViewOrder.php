<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    // Filament sends every attribute to the browser. Keep internal notes away from riders.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (auth()->user()?->isRider()) {
            unset($data['internal_notes']);
        }

        return $data;
    }
}