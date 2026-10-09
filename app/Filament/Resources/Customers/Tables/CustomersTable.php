<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Services\CustomerAnonymiseService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->placeholder('No name'),
                TextColumn::make('phone_raw')->label('WhatsApp')
                    ->searchable(['phone_raw', 'phone_e164', 'phone_search']),
                IconColumn::make('phone_valid')->label('Number OK')->boolean()
                    ->state(fn (Customer $record) => filled($record->phone_e164)),
                TextColumn::make('area.name')->sortable(),
                TextColumn::make('orders_count')->label('Orders')->sortable(),
                TextColumn::make('total_spent')->money('SAR')->sortable(),
                TextColumn::make('last_order_at')->dateTime('d M Y')->sortable()->placeholder('Never'),
                IconColumn::make('whatsapp_opt_in')->label('Opted in')->boolean(),
                IconColumn::make('requires_prepay')->label('Prepay')->boolean(),
            ])
            ->defaultSort('last_order_at', 'desc')
            ->filters([
                SelectFilter::make('area')->relationship('area', 'name'),
                TernaryFilter::make('whatsapp_opt_in')->label('Opted in to offers'),
                TernaryFilter::make('requires_prepay')->label('Prepay required'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('whatsapp')
                    ->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (Customer $record) => 'https://wa.me/'.ltrim((string) $record->phone_e164, '+'))
                    ->openUrlInNewTab()
                    ->visible(fn (Customer $record) => filled($record->phone_e164)),
                // Spec 7: delete on request. Admin only. Scrubs personal data, keeps order totals.
                Action::make('anonymise')
                    ->label('Delete personal data')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this customer\'s personal data?')
                    ->modalDescription('Name, number, email, location and screenshots are removed from the customer and their orders. Order totals stay for reports. This cannot be undone.')
                    ->visible(fn (Customer $record) => (bool) auth()->user()?->isAdmin() && ! $record->trashed())
                    ->action(function (Customer $record) {
                        abort_unless(auth()->user()?->isAdmin(), 403);

                        try {
                            app(CustomerAnonymiseService::class)->anonymise($record);
                            Notification::make()->title('Personal data deleted')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title('Not deleted')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('flagPrepay')
                        ->label('Flag as prepay required')
                        ->requiresConfirmation()
                        ->authorizeIndividualRecords('update')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            Customer::query()->whereKey($records->modelKeys())->update(['requires_prepay' => true]);
                            Notification::make()->title('Customers flagged')->success()->send();
                        }),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}