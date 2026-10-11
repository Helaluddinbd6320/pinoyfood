<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

/** Customer receipt and rider slip. Everything comes from the saved order, never from the browser. */
class OrderPdfService
{
    /** @return array<string, mixed> */
    public function data(Order $order): array
    {
        $order->loadMissing('items', 'area', 'payments');

        $unverified = $order->payments->contains(fn ($p) => in_array($p->status, [
            PaymentStatus::AwaitingProof, PaymentStatus::AwaitingVerification,
        ], true));

        return [
            'order' => $order,
            'shop' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'email' => config('services.contact.email'),
                'phone' => config('services.contact.phone'),
            ],
            'status' => OrderStatusService::current($order),
            'unverifiedPrepay' => $unverified,
            'mapsUrl' => OrdersTable::mapsUrl($order),
        ];
    }

    public function receipt(Order $order): string
    {
        return Pdf::loadView('pdf.receipt', $this->data($order))->setPaper('a5')->output();
    }

    public function slip(Order $order): string
    {
        return Pdf::loadView('pdf.slip', $this->data($order))->setPaper('a5')->output();
    }
}