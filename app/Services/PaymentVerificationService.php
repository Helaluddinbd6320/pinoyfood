<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Only verified ("paid") payments count (spec 6.2). */
class PaymentVerificationService
{
    public function approve(Payment $payment, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $by): Payment {
            $locked = $this->lock($payment, 'approved');

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'reject_reason' => null,
            ])->save();

            $this->recalculate((int) $locked->order_id);

            return $locked;
        });
    }

    /**
     * Step 13: staff took the order by phone or chat and saw the transfer arrive themselves,
     * so there is no screenshot to wait for. Still counted as verified, with the staff member on record.
     */
    public function markPaidByStaff(Payment $payment, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $by): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! in_array($locked->status, [PaymentStatus::AwaitingProof, PaymentStatus::AwaitingVerification], true)) {
                throw new InvalidArgumentException('This payment was already handled.');
            }

            $payload = (array) ($locked->payload ?? []);
            $payload['entered_by_staff'] = true;

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'reject_reason' => null,
                'payload' => $payload,
            ])->save();

            $this->recalculate((int) $locked->order_id);

            return $locked;
        });
    }

    public function reject(Payment $payment, User $by, string $reason): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Enter a reason for the customer.');
        }

        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $locked = $this->lock($payment, 'rejected');

            $locked->forceFill([
                'status' => PaymentStatus::Rejected,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'reject_reason' => mb_substr($reason, 0, 255),
            ])->save();

            return $locked;
        });
    }

    private function lock(Payment $payment, string $verb): Payment
    {
        $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

        if ($locked->status !== PaymentStatus::AwaitingVerification) {
            throw new InvalidArgumentException("Only payments awaiting verification can be {$verb}.");
        }

        return $locked;
    }

    /** paid_online_total = sum of verified payments. The rider collects the rest. */
    private function recalculate(int $orderId): void
    {
        $order = Order::query()->lockForUpdate()->findOrFail($orderId);

        $paid = number_format(
            (float) DB::table('payments')->where('order_id', $orderId)->where('status', PaymentStatus::Paid->value)->sum('amount'),
            2, '.', ''
        );
        $due = bcsub((string) $order->total, $paid, 2);

        $order->forceFill([
            'paid_online_total' => $paid,
            'amount_due_on_delivery' => bccomp($due, '0', 2) === 1 ? $due : '0.00',
        ])->save();
    }
}