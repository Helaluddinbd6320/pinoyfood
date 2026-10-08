<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Services\WhatsappConsentService;
use App\Services\WhatsappLinkService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsappOffers extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'WhatsApp offers';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'WhatsApp offers';

    protected string $view = 'filament.pages.whatsapp-offers';

    /** One offer a week keeps the number safe (spec 6.7). */
    private const RECENT_DAYS = 7;

    /** {name} is replaced for each customer. */
    public string $text = '';

    /** Admin and staff only. Riders never see this page. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isBackOffice();
    }

    public function mount(): void
    {
        $this->text = "Kumusta {name}! Fresh pandesal and chicken adobo are ready this Friday. See this week's offers: "
            .route('home', ['filter' => 'offers']);
    }

    public function markSent(int $customerId): void
    {
        $customer = $this->eligible($customerId);

        if (! $customer) {
            return;
        }

        $now = now();

        DB::table('whatsapp_messages')->insert([
            'customer_id' => $customer->id,
            'direction' => 'out',
            'kind' => 'offer',
            'body' => $this->messageFor($customer),
            'status' => 'sent',
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Notification::make()->title('Marked as sent')->success()->send();
    }

    public function recordStop(int $customerId, WhatsappConsentService $consent): void
    {
        $customer = $this->eligible($customerId);

        if (! $customer) {
            return;
        }

        $consent->recordStop($customer, 'admin');

        Notification::make()->title('Opted out. This customer will not get offers.')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $links = app(WhatsappLinkService::class);

        $customers = Customer::query()
            ->where('whatsapp_opt_in', true)
            ->whereNotNull('phone_e164')
            ->orderBy('name')
            ->limit(300)
            ->get();

        $last = $customers->isEmpty() ? collect() : DB::table('whatsapp_messages')
            ->where('kind', 'offer')
            ->where('direction', 'out')
            ->whereIn('customer_id', $customers->pluck('id'))
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(sent_at) as last_at')
            ->pluck('last_at', 'customer_id');

        $rows = $customers->map(function (Customer $c) use ($links, $last) {
            $at = $last->get($c->id) ? Carbon::parse($last->get($c->id)) : null;

            return [
                'customer' => $c,
                'link' => $links->chat($c->phone_e164, $this->messageFor($c)),
                'last' => $at,
                'recent' => $at !== null && $at->gt(now()->subDays(self::RECENT_DAYS)),
            ];
        });

        return ['rows' => $rows, 'preview' => $links->offerText(Str::limit($this->text, 1000, ''))];
    }

    private function messageFor(Customer $customer): string
    {
        $name = Str::before(trim((string) $customer->name), ' ') ?: 'kababayan';

        return app(WhatsappLinkService::class)->offerText(
            str_replace('{name}', $name, Str::limit($this->text, 1000, ''))
        );
    }

    /** Only customers still opted in with a valid number, never from browser-supplied data. */
    private function eligible(int $customerId): ?Customer
    {
        return Customer::query()
            ->whereKey($customerId)
            ->where('whatsapp_opt_in', true)
            ->whereNotNull('phone_e164')
            ->first();
    }
}