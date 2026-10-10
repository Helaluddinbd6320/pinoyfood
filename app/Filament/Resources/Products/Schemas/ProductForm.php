<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')->columns(2)->schema([
                Select::make('category_id')
                    ->relationship('category', 'name')->required()->preload(),
                TextInput::make('name')
                    ->required()->maxLength(150)
                    ->live(onBlur: true)
                    // create ও edit দুই ক্ষেত্রেই নাম বদলালে slug অটো বদলাবে
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('name_tl')->label('Tagalog name')->maxLength(150),
                TextInput::make('slug')
                    ->required()->maxLength(160)->unique(ignoreRecord: true)
                    // edit পেজ খোলার সময় slug খালি থাকলে নাম থেকে ভরে দেয়
                    ->afterStateHydrated(function (Get $get, Set $set, ?string $state) {
                        if (blank($state) && filled($get('name'))) {
                            $set('slug', Str::slug((string) $get('name')));
                        }
                    }),
                Textarea::make('description')->columnSpanFull(),
                Textarea::make('ingredients')->columnSpanFull(),
                TagsInput::make('allergens'),
                Select::make('storage_type')
                    ->options(['ambient' => 'Ambient', 'chilled' => 'Chilled', 'frozen' => 'Frozen'])
                    ->default('ambient')->required(),
                TextInput::make('shelf_life_days')->numeric()->integer()->minValue(1),
            ]),

            Section::make('Price')->columns(2)->schema([
                TextInput::make('price')
                    ->required()->numeric()->minValue(0.01)->prefix('SAR')->live(onBlur: true),
                TextInput::make('cost')
                    ->numeric()->minValue(0)->prefix('SAR')->live(onBlur: true)
                    ->helperText('Used for margin reports. Never shown to customers.'),
            ]),

            Section::make('Offer')
                ->description('Optional timed offer. The site picks the right price by itself and the offer ends on its own.')
                ->columns(3)
                ->schema([
                    TextInput::make('sale_price')
                        ->numeric()->minValue(0.01)->lt('price')->prefix('SAR')
                        ->live(onBlur: true)
                        ->helperText(function (Get $get) {
                            $sale = $get('sale_price');
                            $cost = $get('cost');

                            if (is_numeric($sale) && is_numeric($cost) && bccomp((string) $sale, (string) $cost, 2) < 0) {
                                return 'Warning: this offer price is below your cost.';
                            }

                            return 'Must be lower than the normal price. Leave empty for no offer.';
                        }),
                    DateTimePicker::make('sale_starts_at')->seconds(false)
                        ->helperText('Empty = starts now.'),
                    DateTimePicker::make('sale_ends_at')->seconds(false)->after('sale_starts_at')
                        ->helperText('Empty = runs until you remove it.'),
                ]),

            Section::make('Stock')->columns(3)->schema([
                TextInput::make('stock')->required()->numeric()->integer()->minValue(0)->default(0),
                TextInput::make('low_stock_threshold')->numeric()->integer()->minValue(0)->default(5),
                Toggle::make('track_stock')->default(true)
                    ->helperText('Off = never shows Sold out.'),
            ]),

            Section::make('Display')->columns(2)->schema([
                FileUpload::make('image_path')
                    ->label('Photo')->image()->disk('public')->directory('products')->visibility('public')
                    ->maxSize(2048),
                Toggle::make('is_active')->label('Show on the website')->default(true),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0),
            ]),
        ]);
    }
}