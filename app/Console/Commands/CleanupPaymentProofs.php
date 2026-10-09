<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentProofService;
use Illuminate\Console\Command;

class CleanupPaymentProofs extends Command
{
    protected $signature = 'payments:cleanup-proofs {--days=90}';

    protected $description = 'Delete payment screenshots 90 days after the order was delivered or cancelled (spec 7)';

    public function handle(PaymentProofService $proofs): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $n = 0;

        Payment::query()
            ->whereNotNull('proof_path')
            ->whereHas('order', fn ($q) => $q->where(
                fn ($w) => $w->where('delivered_at', '<', $cutoff)->orWhere('cancelled_at', '<', $cutoff)
            ))
            ->each(function (Payment $payment) use ($proofs, &$n): void {
                $proofs->purge($payment);
                $n++;
            });

        $this->info("Screenshots removed: {$n}");

        return self::SUCCESS;
    }
}