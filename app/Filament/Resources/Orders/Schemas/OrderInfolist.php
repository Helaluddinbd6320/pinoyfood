<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order_no'),
                TextEntry::make('customer.name')
                    ->label('Customer'),
                TextEntry::make('source')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('customer_name'),
                TextEntry::make('phone_raw')
                    ->placeholder('-'),
                TextEntry::make('phone_e164')
                    ->placeholder('-'),
                TextEntry::make('area.name')
                    ->label('Area')
                    ->placeholder('-'),
                TextEntry::make('address_notes')
                    ->placeholder('-'),
                TextEntry::make('location_pin_url')
                    ->placeholder('-'),
                TextEntry::make('lat')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('lng')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('delivery_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('slot_label')
                    ->placeholder('-'),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('delivery_fee')
                    ->numeric(),
                IconEntry::make('free_delivery_applied')
                    ->boolean(),
                TextEntry::make('discount_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('paid_online_total')
                    ->numeric(),
                TextEntry::make('amount_due_on_delivery')
                    ->numeric(),
                TextEntry::make('delivery_payment_method')
                    ->placeholder('-'),
                TextEntry::make('collected_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('collected_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('rider.name')
                    ->label('Rider')
                    ->placeholder('-'),
                TextEntry::make('customer_notes')
                    ->placeholder('-'),
                TextEntry::make('internal_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('confirmed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('out_for_delivery_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('delivered_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancelled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancel_reason')
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
