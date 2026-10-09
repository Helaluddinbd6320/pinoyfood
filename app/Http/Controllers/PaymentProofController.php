<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

class PaymentProofController extends Controller
{
    /** Staff only (spec 7). Riders and customers never see screenshots. */
    public function show(Payment $payment)
    {
        abort_unless(auth('web')->user()?->isBackOffice(), 403);

        $disk = Storage::disk('local');
        abort_if(blank($payment->proof_path) || ! $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, 'proof-'.$payment->id.'.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Customer page behind a signed, expiring link (sent on WhatsApp). */
    public function upload(int $payment)
    {
        $payment = Payment::query()->with('order')->findOrFail($payment);

        return view('storefront.payment-proof', ['payment' => $payment, 'order' => $payment->order]);
    }
}