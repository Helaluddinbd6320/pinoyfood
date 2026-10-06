<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes the `settings` table (spec 4.10) through the cache.
 * Uses the query builder with explicit JSON so it does not depend on
 * how the Setting model casts `value`.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.all';

    public const DEFAULTS = [
        'free_delivery_enabled' => true,
        'free_delivery_min' => '150.00',
        'delivery_fee' => '10.00',
        'allow_prepay_delivery_fee' => true,
        'require_deposit_for_free_delivery' => false,
        'deposit_amount' => '10.00',
        'min_order_amount' => '0.00',
        'prepay_instructions' => '',
    ];

    private const TYPES = [
        'free_delivery_enabled' => 'bool',
        'free_delivery_min' => 'money',
        'delivery_fee' => 'money',
        'allow_prepay_delivery_fee' => 'bool',
        'require_deposit_for_free_delivery' => 'bool',
        'deposit_amount' => 'money',
        'min_order_amount' => 'money',
        'prepay_instructions' => 'text',
    ];

    /** @return array<string, mixed> every key, typed, defaults filled in */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return DB::table('settings')
                ->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))
                ->all();
        });

        $raw = array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        $out = [];
        foreach (self::TYPES as $key => $type) {
            $out[$key] = match ($type) {
                'bool' => filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN),
                'money' => Money::fromCents(Money::toCents($raw[$key])),
                'text' => (string) $raw[$key],
            };
        }

        return $out;
    }

    public function get(string $key): mixed
    {
        $this->assertKnown($key);

        return $this->all()[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /** All values are validated first, so a bad value saves nothing. */
    public function setMany(array $values): void
    {
        $rows = [];
        $stamp = now()->format('Y-m-d H:i:s');

        foreach ($values as $key => $value) {
            $this->assertKnown($key);
            $rows[] = [
                'key' => $key,
                'value' => json_encode($this->normalize($key, $value)),
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('settings')->upsert($rows, ['key'], ['value', 'updated_at']);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function freeDeliveryEnabled(): bool
    {
        return $this->get('free_delivery_enabled');
    }

    public function freeDeliveryMin(): string
    {
        return $this->get('free_delivery_min');
    }

    public function deliveryFee(): string
    {
        return $this->get('delivery_fee');
    }

    public function allowPrepayDeliveryFee(): bool
    {
        return $this->get('allow_prepay_delivery_fee');
    }

    public function requireDepositForFreeDelivery(): bool
    {
        return $this->get('require_deposit_for_free_delivery');
    }

    public function depositAmount(): string
    {
        return $this->get('deposit_amount');
    }

    public function minOrderAmount(): string
    {
        return $this->get('min_order_amount');
    }

    public function prepayInstructions(): string
    {
        return $this->get('prepay_instructions');
    }

    private function assertKnown(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }
    }

    private function normalize(string $key, mixed $value): mixed
    {
        return match (self::TYPES[$key]) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? throw new InvalidArgumentException("Setting [{$key}] must be true or false."),
            'money' => is_numeric($value) && $value >= 0
                ? Money::fromCents(Money::toCents($value))
                : throw new InvalidArgumentException("Setting [{$key}] must be a number of 0 or more."),
            'text' => (string) $value,
        };
    }
}