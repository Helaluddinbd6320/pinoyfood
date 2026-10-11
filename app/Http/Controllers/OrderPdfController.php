<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPdfService;
use Illuminate\Http\Response;

class OrderPdfController extends Controller
{
    /** Customer: behind a signed, expiring link. No phone number appears in the URL. */
    public function receipt(string $orderNo, OrderPdfService $pdf): Response
    {
        $order = Order::query()->where('order_no', $orderNo)->firstOrFail();

        return $this->send($pdf->receipt($order), "receipt-{$order->order_no}.pdf");
    }

    /** Admin and staff only. */
    public function staffReceipt(Order $order, OrderPdfService $pdf): Response
    {
        abort_unless(auth('web')->user()?->isBackOffice(), 403);

        return $this->send($pdf->receipt($order), "receipt-{$order->order_no}.pdf");
    }

    /** Admin, staff, or the rider the order is assigned to (OrderPolicy::view). */
    public function slip(Order $order, OrderPdfService $pdf): Response
    {
        abort_unless(auth('web')->user()?->can('view', $order), 403);

        return $this->send($pdf->slip($order), "slip-{$order->order_no}.pdf");
    }

    private function send(string $bytes, string $name): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}