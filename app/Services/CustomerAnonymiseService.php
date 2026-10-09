<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * "Delete on request" (spec 7). Orders are never deleted, so personal details are scrubbed
 * from the customer and from the snapshots on their orders, then the customer is soft-deleted.
 */
class CustomerAnonymiseService
{
    public const REMOVED_NAME = 'Deleted customer';

    /** An order the shop may still need to contact the customer about. */
    private const OPEN = ['new', 'confirmed', 'out_for_delivery', 'no_answer'];

    public function __construct(private readonly PaymentProofService $proofs)
    {
    }

    public function anonymise(Customer $customer): void
    {
        if (Order::query()->where('customer_id', $customer->id)->whereIn('status', self::OPEN)->exists()) {
            throw new DomainException('This customer has an open order. Finish or cancel it first.');
        }

        // Screenshots can show names and account details: delete the files first.
        Payment::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereNotNull('proof_path')
            ->each(fn (Payment $payment) => $this->proofs->purge($payment));

        DB::transaction(function () use ($customer): void {
            Order::query()->where('customer_id', $customer->id)->update([
                'customer_name' => self::REMOVED_NAME,
                'phone_raw' => '',
                'phone_e164' => null,
                'address_notes' => null,
                'location_pin_url' => null,
                'lat' => null,
                'lng' => null,
                'customer_notes' => null,
                'internal_notes' => null,
            ]);

            Payment::query()
                ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
                ->update(['customer_reference' => null]);

            DB::table('whatsapp_messages')->where('customer_id', $customer->id)->update(['body' => '[removed]']);
            DB::table('consent_logs')->where('customer_id', $customer->id)->update(['ip_address' => null, 'user_agent' => null]);

            // referral_code stays (required and unique). Aggregates stay for reports.
            $customer->forceFill([
                'name' => self::REMOVED_NAME,
                'email' => null,
                'google_id' => null,
                'avatar_url' => null,
                'phone_raw' => null,
                'phone_e164' => null,
                'phone_search' => null,
                'address_notes' => null,
                'default_pin_url' => null,
                'default_lat' => null,
                'default_lng' => null,
                'whatsapp_opt_in' => false,
                'internal_notes' => null,
            ])->save();

            $customer->delete();
        });
    }
}