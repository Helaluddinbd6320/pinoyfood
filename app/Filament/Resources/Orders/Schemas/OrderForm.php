<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(3)->schema([
                TextInput::make('order_no')->label('Order')->disabled()->dehydrated(false),
                TextInput::make('status')->disabled()->dehydrated(false)
                    ->formatStateUsing(fn ($state) => OrdersTable::statusLabel($state)),
                TextInput::make('source')->disabled()->dehydrated(false)
                    ->formatStateUsing(fn ($state) => OrdersTable::sourceLabel($state)),
                DatePicker::make('delivery_date'),
                TextInput::make('slot_label')->label('Time slot')->maxLength(40),
                Select::make('rider_id')->label('Rider')
                    ->options(fn () => User::query()->riders()->pluck('name', 'id'))
                    ->placeholder('Unassigned'),
            ]),

            Section::make('Customer and delivery')->columns(2)->schema([
                TextInput::make('customer_name')->disabled()->dehydrated(false),
                TextInput::make('phone_raw')->label('WhatsApp')->disabled()->dehydrated(false),
                Select::make('area_id')->relationship('area', 'name')->preload(),
                TextInput::make('address_notes')->maxLength(255),
                TextInput::make('location_pin_url')->label('Location pin')
                    ->maxLength(500)->rule('regex:/^https?:\/\//i')->columnSpanFull(),
                TextInput::make('customer_notes')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('internal_notes')->columnSpanFull()
                    ->visible(fn () => ! auth()->user()?->isRider()),
            ]),

            Section::make('Totals')->columns(4)->schema([
                TextInput::make('subtotal')->prefix('SAR')->disabled()->dehydrated(false),
                TextInput::make('delivery_fee')->prefix('SAR')->disabled()->dehydrated(false),
                TextInput::make('total')->prefix('SAR')->disabled()->dehydrated(false),
                TextInput::make('amount_due_on_delivery')->label('Rider collects')
                    ->prefix('SAR')->disabled()->dehydrated(false),
            ]),
        ]);
    }
}