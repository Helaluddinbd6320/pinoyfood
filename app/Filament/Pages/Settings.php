<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Settings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    // Keep whatever `$view` line the generator wrote for your version if it differs.
    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    private const BOOLEANS = [
        'free_delivery_enabled', 'allow_prepay_delivery_fee', 'require_deposit_for_free_delivery',
    ];

    private const MONEY = [
        'free_delivery_min', 'delivery_fee', 'deposit_amount', 'min_order_amount',
    ];

    private const TEXT = ['prepay_instructions'];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->form->fill(
            collect([...self::BOOLEANS, ...self::MONEY, ...self::TEXT])
                ->mapWithKeys(fn (string $key) => [$key => $settings->get($key)]) // adjust if your service differs
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Delivery charges')->columns(2)->schema([
                    Toggle::make('free_delivery_enabled')->label('Free delivery offer'),
                    TextInput::make('free_delivery_min')->label('Free delivery above')
                        ->required()->numeric()->minValue(0)->prefix('SAR'),
                    TextInput::make('delivery_fee')->label('Delivery fee')
                        ->required()->numeric()->minValue(0)->prefix('SAR')
                        ->helperText('Areas can override this fee.'),
                    TextInput::make('min_order_amount')->label('Minimum order')
                        ->required()->numeric()->minValue(0)->prefix('SAR')
                        ->helperText('0 = no minimum.'),
                ]),
                Section::make('Advance payment')->columns(2)->schema([
                    Toggle::make('allow_prepay_delivery_fee')->label('Allow paying the delivery fee in advance'),
                    Toggle::make('require_deposit_for_free_delivery')->label('Ask for a deposit on free delivery orders'),
                    TextInput::make('deposit_amount')->label('Deposit amount')
                        ->required()->numeric()->minValue(0)->prefix('SAR'),
                    Textarea::make('prepay_instructions')->label('Payment instructions shown to customers')
                        ->rows(4)->maxLength(1000)->columnSpanFull(),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings = app(SettingsService::class);

        foreach (self::BOOLEANS as $key) {
            $settings->set($key, (bool) ($state[$key] ?? false)); // adjust if your service differs
        }

        foreach (self::MONEY as $key) {
            $settings->set($key, bcadd((string) $state[$key], '0', 2));
        }

        foreach (self::TEXT as $key) {
            $settings->set($key, (string) ($state[$key] ?? ''));
        }

        Notification::make()->title('Settings saved')->success()->send();
    }
}