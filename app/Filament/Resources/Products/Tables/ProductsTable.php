<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    /**
     * Offer price for a percent discount, rounded half up to 2 decimals (bcmath, no floats).
     * Returns null when the result would not be a real offer (not above 0 and below the price).
     */
    public static function offerPrice(string $price, int $percentOff): ?string
    {
        if ($percentOff < 1 || $percentOff > 90 || ! is_numeric($price)) {
            return null;
        }

        $raw = bcdiv(bcmul($price, (string) (100 - $percentOff), 4), '100', 4);
        $sale = bcadd(bcadd($raw, '0.005', 3), '0', 2);

        return (bccomp($sale, '0.00', 2) === 1 && bccomp($sale, $price, 2) === -1) ? $sale : null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->sortable(),
                TextColumn::make('price')->money('SAR')->sortable(),
                TextColumn::make('effective_price')->label('Selling price')->money('SAR'),
                TextColumn::make('discount_percent')->label('Offer')->badge()->color('warning')
                    ->formatStateUsing(fn ($state) => $state ? "-{$state}%" : null),
                TextColumn::make('stock')->badge()->sortable()
                    ->color(fn (Product $record) => $record->stock <= 0
                        ? 'danger'
                        : ($record->stock <= $record->low_stock_threshold ? 'warning' : 'success')),
                ToggleColumn::make('is_active')->label('Shown'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name'),
                Filter::make('on_offer')->label('On offer now')->toggle()
                    ->query(fn (Builder $query) => $query->onOfferNow()),
                Filter::make('low_stock')->label('Low stock')->toggle()
                    ->query(fn (Builder $query) => $query->where('track_stock', true)
                        ->whereColumn('stock', '<=', 'low_stock_threshold')),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::setOfferBulkAction(),
                    self::clearOfferBulkAction(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function setOfferBulkAction(): BulkAction
    {
        return BulkAction::make('setOffer')
            ->label('Set offer')
            ->schema([
                TextInput::make('percent_off')->label('Percent off')
                    ->required()->numeric()->integer()->minValue(1)->maxValue(90)->suffix('%'),
                DateTimePicker::make('starts_at')->seconds(false)->helperText('Empty = starts now.'),
                DateTimePicker::make('ends_at')->seconds(false)->after('starts_at')
                    ->helperText('Empty = runs until you clear it.'),
            ])
            ->authorizeIndividualRecords('update')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) {
                $done = 0;
                $skipped = 0;

                foreach ($records as $product) {
                    $sale = self::offerPrice((string) $product->price, (int) $data['percent_off']);

                    if ($sale === null) {
                        $skipped++;
                        continue;
                    }

                    Product::query()->whereKey($product->getKey())->update([
                        'sale_price' => $sale,
                        'sale_starts_at' => $data['starts_at'] ?? null,
                        'sale_ends_at' => $data['ends_at'] ?? null,
                    ]);
                    $done++;
                }

                Notification::make()
                    ->title("Offer set on {$done} product(s)")
                    ->body($skipped ? "{$skipped} skipped: the discounted price would not be below the normal price." : null)
                    ->{$skipped ? 'warning' : 'success'}()
                    ->send();
            });
    }

    private static function clearOfferBulkAction(): BulkAction
    {
        return BulkAction::make('clearOffer')
            ->label('Clear offer')
            ->requiresConfirmation()
            ->authorizeIndividualRecords('update')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                Product::query()->whereKey($records->modelKeys())->update([
                    'sale_price' => null,
                    'sale_starts_at' => null,
                    'sale_ends_at' => null,
                ]);

                Notification::make()->title('Offers cleared')->success()->send();
            });
    }
}