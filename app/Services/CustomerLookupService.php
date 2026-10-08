<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Finds a customer by phone number, or creates one.
 *
 * Lookup order (spec section 5):
 *   1. exact phone_e164
 *   2. phone_search (last 9 digits), only if exactly one customer matches
 *   3. otherwise create a new customer
 */
class CustomerLookupService
{
    /** Only these extra fields can be passed in. Never pass raw request data. */
    private const ALLOWED = [
        'name',
        'email',
        'google_id',
        'avatar_url',
        'area_id',
        'address_notes',
        'default_pin_url',
        'default_lat',
        'default_lng',
    ];

    // No 0, O, 1 or I, to avoid mix-ups when a customer reads the code aloud.
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly PhoneNormalizer $phones) {}

    /**
     * Find only. Soft-deleted customers are not returned.
     */
    public function find(?string $phone): ?Customer
    {
        return $this->locate($this->phones->normalize($phone), false);
    }

    /**
     * Find, or create. The phone number must contain digits, but its format
     * never matters. A number we cannot validate is stored with phone_e164 null.
     *
     * @param  array<string, mixed>  $attributes  extra fields, used only when a new customer is created
     */
    public function findOrCreate(?string $phone, array $attributes = []): Customer
    {
        $n = $this->phones->normalize($phone);

        if ($n['search'] === null) {
            throw new InvalidArgumentException('A phone number with digits is required.');
        }

        $existing = $this->locate($n, true);

        if ($existing !== null) {
            return $this->refreshExisting($existing, $n);
        }

        try {
            return Customer::create(array_merge(Arr::only($attributes, self::ALLOWED), [
                'phone_raw' => $n['raw'],
                'phone_e164' => $n['e164'],
                'phone_search' => $n['search'],
                'referral_code' => $this->newReferralCode(),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // Two requests created the same number at the same moment.
            $existing = $this->locate($n, true);

            if ($existing === null) {
                throw $e;
            }

            return $this->refreshExisting($existing, $n);
        }
    }

    /**
     * @param  array{raw: ?string, e164: ?string, search: ?string}  $n
     */
    private function locate(array $n, bool $withTrashed): ?Customer
    {
        $query = fn() => $withTrashed ? Customer::withTrashed() : Customer::query();

        if ($n['e164'] !== null) {
            $exact = $query()->where('phone_e164', $n['e164'])->first();

            if ($exact !== null) {
                return $exact;
            }
        }

        if ($n['search'] === null) {
            return null;
        }

        $fallback = $query()->where('phone_search', $n['search']);

        // A valid number never matches a customer who already has a different
        // valid number (same last 9 digits, different country code).
        if ($n['e164'] !== null) {
            $fallback->whereNull('phone_e164');
        }

        $matches = $fallback->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  array{raw: ?string, e164: ?string, search: ?string}  $n
     */
    private function refreshExisting(Customer $customer, array $n): Customer
    {
        if ($customer->trashed()) {
            $customer->restore();
        }

        // The stored number was unusable before and this one is valid: fill it in.
        if ($customer->phone_e164 === null && $n['e164'] !== null) {
            $customer->phone_e164 = $n['e164'];
            $customer->save();
        }

        return $customer;
    }

    public function newReferralCode(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;

        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }
        } while (Customer::withTrashed()->where('referral_code', $code)->exists());

        return $code;
    }
}
