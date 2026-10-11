<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Services\WhatsappLinkService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrdersTable
{
    public const STATUSES = [
        'new' => 'New',
        'confirmed' => 'Confirmed',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'no_answer' => 'No answer',
    ];

    public const SOURCES = [
        'website' => 'Website',
        'whatsapp' => 'WhatsApp',
        'call' => 'Call',
        'admin' => 'Admin',
    ];

    /** Columns cast to enums give an enum object; always compare and show the string value. */
    public static function raw(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (string) $state;
    }

    public static function statusLabel(mixed $state): string
    {
        return self::STATUSES[self::raw($state)] ?? Str::headline(self::raw($state));
    }

    public static function sourceLabel(mixed $state): string
    {
        return self::SOURCES[self::raw($state)] ?? Str::headline(self::raw($state));
    }

    public static function mapsUrl(Order $order): ?string
    {
        $pin = (string) $order->location_pin_url;

        // Only real web links: a pasted "javascript:" value must never become a clickable link.
        if ($pin !== '' && Str::startsWith(Str::lower($pin), ['https://', 'http://'])) {
            return $pin;
        }

        if ($order->lat !== null && $order->lng !== null) {
            return 'https://maps.google.com/?q='.$order->lat.','.$order->lng;
        }

        return null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')->label('#')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Placed')->dateTime('d M, H:i')->sortable(),
                TextColumn::make('customer_name')->label('Customer')->searchable(),
                TextColumn::make('phone_raw')->label('WhatsApp')
                    ->searchable(['phone_raw', 'phone_e164', 'phone_search']),
                TextColumn::make('area.name')->label('Area'),
                TextColumn::make('source')->badge()
                    ->formatStateUsing(fn ($state) => self::sourceLabel($state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => self::statusLabel($state))
                    ->color(fn ($state) => match (self::raw($state)) {
                        'new' => 'warning',
                        'confirmed' => 'info',
                        'out_for_delivery' => 'primary',
                        'delivered' => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('total')->money('SAR')->sortable(),
                TextColumn::make('amount_due_on_delivery')->label('Rider collects')->money('SAR'),
                IconColumn::make('prepaid')->boolean()
                    ->state(fn (Order $record) => bccomp((string) $record->paid_online_total, '0', 2) === 1),
                TextColumn::make('rider.name')->placeholder('Unassigned')
                    ->visible(fn () => ! auth()->user()?->isRider()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('source')->options(self::SOURCES),
                SelectFilter::make('area')->relationship('area', 'name'),
                SelectFilter::make('rider')->relationship('rider', 'name')
                    ->visible(fn () => ! auth()->user()?->isRider()),
                Filter::make('delivery_day')
                    ->schema([DatePicker::make('on')->label('Delivery day')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['on'] ?? null,
                        fn (Builder $q, $day) => $q->whereDate('delivery_date', $day)
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    self::statusAction('confirm', 'Confirm', 'confirmed', 'info', Heroicon::OutlinedCheckCircle),
                    self::statusAction('outForDelivery', 'Out for delivery', 'out_for_delivery', 'primary', Heroicon::OutlinedTruck),
                    self::statusAction('delivered', 'Delivered', 'delivered', 'success', Heroicon::OutlinedCheckCircle),
                    self::statusAction('noAnswer', 'No answer', 'no_answer', 'warning', Heroicon::OutlinedXCircle),
                    self::cancelAction(),
                    self::assignRiderAction(),
                    // Step 16: PDFs. Receipt for admin and staff, slip also for the assigned rider.
                    Action::make('receiptPdf')->label('Receipt PDF')->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (Order $record) => route('staff.orders.receipt', $record))
                        ->openUrlInNewTab()
                        ->visible(fn () => (bool) auth()->user()?->isBackOffice()),
                    Action::make('slipPdf')->label('Delivery slip')->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Order $record) => route('staff.orders.slip', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Order $record) => auth()->user()?->can('view', $record) ?? false),
                    Action::make('maps')->label('Open in Maps')->icon(Heroicon::OutlinedMapPin)
                        ->url(fn (Order $record) => self::mapsUrl($record))
                        ->openUrlInNewTab()
                        ->visible(fn (Order $record) => self::mapsUrl($record) !== null),
                    // Step 11: opens the chat with a ready message for the order's current status.
                    Action::make('whatsapp')->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->url(fn (Order $record) => app(WhatsappLinkService::class)
                            ->chat($record->phone_e164, app(WhatsappLinkService::class)->orderStatusText($record)))
                        ->openUrlInNewTab()
                        ->visible(fn (Order $record) => filled($record->phone_e164)),
                ]),
            ]);
        // No toolbarActions: orders are never bulk deleted.
    }

    private static function statusAction(string $name, string $label, string $to, string $color, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)->color($color)->icon($icon)
            ->requiresConfirmation()
            ->visible(fn (Order $record) => OrderStatusService::canMove($record, $to)
                && (auth()->user()?->can('changeStatus', [$record, $to]) ?? false))
            ->action(function (Order $record) use ($to, $label) {
                Gate::authorize('changeStatus', [$record, $to]);
                self::run(fn () => app(OrderStatusService::class)->move($record, $to), "Order marked: {$label}");
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')->color('danger')->icon(Heroicon::OutlinedXCircle)
            ->schema([Textarea::make('reason')->required()->maxLength(255)])
            ->visible(fn (Order $record) => OrderStatusService::canMove($record, 'cancelled')
                && (auth()->user()?->can('changeStatus', [$record, 'cancelled']) ?? false))
            ->action(function (Order $record, array $data) {
                Gate::authorize('changeStatus', [$record, 'cancelled']);
                self::run(
                    fn () => app(OrderStatusService::class)->move($record, 'cancelled', $data['reason']),
                    'Order cancelled'
                );
            });
    }

    private static function assignRiderAction(): Action
    {
        return Action::make('assignRider')
            ->label('Assign rider')->icon(Heroicon::OutlinedUserPlus)
            ->schema([
                Select::make('rider_id')->label('Rider')->required()
                    ->options(fn () => User::query()->riders()->pluck('name', 'id')),
            ])
            ->visible(fn (Order $record) => auth()->user()?->can('assignRider', $record) ?? false)
            ->action(function (Order $record, array $data) {
                Gate::authorize('assignRider', $record);
                $record->forceFill(['rider_id' => $data['rider_id']])->save();
                Notification::make()->title('Rider assigned')->success()->send();
            });
    }

    private static function run(\Closure $change, string $okMessage): void
    {
        try {
            $change();
            Notification::make()->title($okMessage)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Not allowed')->body($e->getMessage())->danger()->send();
        }
    }
}