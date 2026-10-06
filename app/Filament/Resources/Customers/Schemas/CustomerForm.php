<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('name')->maxLength(120),
                TextInput::make('email')->disabled()->dehydrated(false),
                TextInput::make('phone_raw')->label('WhatsApp (as typed)')->disabled()->dehydrated(false),
                TextInput::make('phone_e164')->label('WhatsApp (clean)')->disabled()->dehydrated(false)
                    ->helperText(fn (?Customer $record) => $record && blank($record->phone_e164)
                        ? 'Check this number: it was not recognised.'
                        : null),
            ]),

            Section::make('Delivery')->columns(2)->schema([
                Select::make('area_id')->relationship('area', 'name')->preload(),
                TextInput::make('address_notes')->maxLength(255),
                TextInput::make('default_pin_url')->label('Saved location pin')
                    ->maxLength(500)->rule('regex:/^https?:\/\//i')->columnSpanFull(),
            ]),

            Section::make('WhatsApp offers')->columns(2)->schema([
                Toggle::make('whatsapp_opt_in')->label('Opted in')->disabled()->dehydrated(false)
                    ->helperText('Changes only through the customer or a STOP reply, so the consent log stays correct.'),
                TextInput::make('opt_in_source')->disabled()->dehydrated(false),
            ]),

            Section::make('Handling')->columns(2)->schema([
                Toggle::make('requires_prepay')->label('Must pay the delivery fee in advance'),
                TextInput::make('no_answer_count')->disabled()->dehydrated(false),
                TextInput::make('orders_count')->disabled()->dehydrated(false),
                TextInput::make('total_spent')->prefix('SAR')->disabled()->dehydrated(false),
                Textarea::make('internal_notes')->columnSpanFull(),
            ]),
        ]);
    }
}