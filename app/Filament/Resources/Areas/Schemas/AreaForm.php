<?php

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('delivery_fee_override')
                ->label('Delivery fee for this area')
                ->helperText('Leave empty to use the global fee from Settings.')
                ->numeric()->minValue(0)->prefix('SAR'),
            TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('We deliver here')->default(true),
        ]);
    }
}