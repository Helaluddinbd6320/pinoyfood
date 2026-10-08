<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Everything the signed-in customer can do (spec 6.3): Google sign-in,
 * attaching a phone number (with merge), WhatsApp offers, order again.
 * The customer always comes from the auth guard, never from browser input.
 */
class CustomerAccountService
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly CustomerLookupService $lookup,
        private readonly CartService $cart,
    ) {
    }

    public static function summary(Order $order): string
    {
        return $order->items->map(fn ($i) => Str::before((string) $i->product_name, ' ('))->implode(', ');
    }

    /** Match by google_id, then by verified email, else create (spec 6.3). */
    public function signInWithGoogle(string $googleId, ?string $email, bool $emailVerified, ?string $name, ?string $avatar): Customer
    {
        $email = ($email !== null && trim($email) !== '' && $emailVerified) ? mb_strtolower(trim($email)) : null;

        $customer = $this->findForGoogle($googleId, $email);

        if ($customer !== null) {
            return $this->refreshFromGoogle($customer, $googleId, $email, $name, $avatar);
        }

        try {
            return Customer::create([
                'google_id' => $googleId,
                'email' => $this->emailIsFree($email, null) ? $email : null,
                'name' => $name !== null ? Str::limit($name, 120, '') : null,
                'avatar_url' => $avatar !== null ? Str::limit($avatar, 500, '') : null,
                'referral_code' => $this->lookup->newReferralCode(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Two callbacks at the same moment: use the record the other one made.
            $customer = $this->findForGoogle($googleId, $email);

            if ($customer === null) {
                throw $e;
            }

            return $this->refreshFromGoogle($customer, $googleId, $email, $name, $avatar);
        }
    }

    /**
     * Give a customer without a phone their number. If another customer already
     * has that number, the two are merged and the survivor is returned.
     *
     * @throws InvalidArgumentException when the number has no digits
     * @throws DomainException when the number belongs to another Google account
     */
    public function attachPhone(Customer $customer, string $phone): Customer
    {
        $n = $this->phones->normalize($phone);

        if ($n['search'] === null) {
            throw new InvalidArgumentException('A phone number with digits is required.');
        }

        if ($customer->phone_search !== null) {
            return $customer;
        }

        $existing = $this->lookup->find($phone);

        if ($existing === null && $n['e164'] !== null) {
            $existing = Customer::onlyTrashed()
                ->where('phone_e164', $n['e164'])
                ->where('id', '!=', $customer->id)
                ->first();
        }

        if ($existing !== null && $existing->id !== $customer->id) {
            return $this->merge($existing, $customer);
        }

        $customer->forceFill([
            'phone_raw' => $n['raw'],
            'phone_e164' => $n['e164'],
            'phone_search' => $n['search'],
        ])->save();

        return $customer;
    }

    /** Switch WhatsApp offers on or off and log it in consent_logs (spec 7). */
    public function setOffers(Customer $customer, bool $on, ?string $ip = null, ?string $userAgent = null): Customer
    {
        if ($on === (bool) $customer->whatsapp_opt_in) {
            return $customer;
        }

        if ($on && blank($customer->phone_search)) {
            throw new DomainException('Add your WhatsApp number first.');
        }

        DB::transaction(function () use ($customer, $on, $ip, $userAgent): void {
            $now = now();

            $customer->forceFill($on
                ? ['whatsapp_opt_in' => true, 'opted_in_at' => $now, 'opted_out_at' => null, 'opt_in_source' => 'account']
                : ['whatsapp_opt_in' => false, 'opted_out_at' => $now]
            )->save();

            DB::table('consent_logs')->insert([
                'customer_id' => $customer->id,
                'action' => $on ? 'opt_in' : 'opt_out',
                'source' => 'account',
                'ip_address' => $ip,
                'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
                'created_at' => $now,
            ]);
        });

        return $customer->refresh();
    }

    /** @return Collection<int, Order> newest first, with items */
    public function recentOrders(Customer $customer, int $limit = 3): Collection
    {
        return $customer->orders()->with('items')->latest('id')->limit($limit)->get();
    }

    /** Most ordered products that can still be bought. */
    public function favorites(Customer $customer, int $limit = 3): Collection
    {
        $ids = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customer->id)
            ->where('orders.status', '!=', 'cancelled')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.qty) DESC')
            ->limit(10)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Product $p) => $ids->search($p->id))
            ->take($limit)
            ->values();
    }

    /**
     * Put an earlier order back into the cart. Only the customer's own orders
     * qualify. Prices are never copied: the cart reprices at current prices.
     *
     * @return array{added:int, skipped:int}
     */
    public function reorder(Customer $customer, int $orderId): array
    {
        $order = $customer->orders()->whereKey($orderId)->with('items')->first();

        if ($order === null) {
            return ['added' => 0, 'skipped' => 0];
        }

        $added = 0;
        $skipped = 0;

        foreach ($order->items as $item) {
            $qty = min((int) $item->qty, 50);

            if (! $item->product_id) {
                $skipped += $qty;

                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                if ($this->cart->add((int) $item->product_id) === 'added') {
                    $added++;
                } else {
                    $skipped += $qty - $i;

                    break;
                }
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    private function findForGoogle(string $googleId, ?string $email): ?Customer
    {
        $customer = Customer::withTrashed()->where('google_id', $googleId)->first();

        if ($customer !== null || $email === null) {
            return $customer;
        }

        $byEmail = Customer::withTrashed()->where('email', $email)->first();

        // An email that already belongs to another Google account is never reused.
        return ($byEmail !== null && $byEmail->google_id === null) ? $byEmail : null;
    }

    private function refreshFromGoogle(Customer $customer, string $googleId, ?string $email, ?string $name, ?string $avatar): Customer
    {
        if ($customer->trashed()) {
            $customer->restore();
        }

        if ($customer->google_id === null) {
            $customer->google_id = $googleId;
        }
        if ($avatar !== null) {
            $customer->avatar_url = Str::limit($avatar, 500, '');
        }
        if (blank($customer->name) && $name !== null) {
            $customer->name = Str::limit($name, 120, '');
        }
        if (blank($customer->email) && $this->emailIsFree($email, $customer->id)) {
            $customer->email = $email;
        }

        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer;
    }

    private function emailIsFree(?string $email, ?int $exceptId): bool
    {
        if ($email === null) {
            return false;
        }

        return ! Customer::withTrashed()
            ->where('email', $email)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * The survivor (holds the phone and the history) takes the Google fields.
     * The Google-only duplicate is soft-deleted and its records move across.
     */
    private function merge(Customer $survivor, Customer $loser): Customer
    {
        if ($survivor->google_id !== null && $loser->google_id !== null && $survivor->google_id !== $loser->google_id) {
            throw new DomainException('That number is already linked to another Google account.');
        }

        return DB::transaction(function () use ($survivor, $loser): Customer {
            $google = [
                'google_id' => $loser->google_id,
                'email' => $loser->email,
                'avatar_url' => $loser->avatar_url,
            ];

            // Free the unique columns before the survivor takes them.
            $loser->forceFill(['google_id' => null, 'email' => null])->save();

            Order::query()->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);
            DB::table('consent_logs')->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);
            DB::table('whatsapp_messages')->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);
            Customer::query()->where('referred_by_customer_id', $loser->id)->update(['referred_by_customer_id' => $survivor->id]);

            $loser->delete();

            if ($survivor->trashed()) {
                $survivor->restore();
            }

            $survivor->google_id ??= $google['google_id'];
            if (blank($survivor->email) && $this->emailIsFree($google['email'], $survivor->id)) {
                $survivor->email = $google['email'];
            }
            if ($google['avatar_url'] !== null) {
                $survivor->avatar_url = $google['avatar_url'];
            }

            $orders = Order::query()->where('customer_id', $survivor->id);
            $survivor->orders_count = (clone $orders)->count();
            $survivor->last_order_at = (clone $orders)->max('created_at');
            $survivor->save();

            return $survivor;
        });
    }
}