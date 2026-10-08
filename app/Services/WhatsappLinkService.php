<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Support\Sar;
use Illuminate\Support\Str;

/**
 * Builds wa.me click-to-chat links and their ready-made messages (spec 6.7 phase 1).
 * Nothing is sent from the server: a person presses the link and sends the message.
 */
class WhatsappLinkService
{
    public function __construct(private readonly PhoneNormalizer $phones)
    {
    }

    /** Digits only, no plus (spec 5), or null when no valid business number is configured. */
    public function businessNumber(): ?string
    {
        $e164 = $this->phones->normalize((string) config('services.whatsapp.business_number'))['e164'];

        return $e164 !== null ? ltrim($e164, '+') : null;
    }

    /** Link to chat with a customer. Null when the number is unusable. */
    public function chat(?string $e164, string $text = ''): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $e164);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    /** Link to chat with the shop (storefront button). */
    public function toBusiness(string $text = ''): ?string
    {
        $number = $this->businessNumber();

        return $number === null ? null : $this->chat($number, $text);
    }

    /**
     * Prices come from the server quote (offers included), never from the browser.
     *
     * @param  array<int, array{name: string, qty: int, unit: string, total: string}>  $lines
     */
    public function cartText(array $lines, string $subtotal, string $delivery = '0.00', ?string $total = null): string
    {
        $rows = array_map(
            fn (array $l) => '- '.$l['qty'].' x '.$l['name'].' ('.Sar::format($l['unit']).' each) = '.Sar::format($l['total']),
            $lines,
        );

        $deliveryLine = bccomp($delivery, '0', 2) === 1
            ? 'Delivery: '.Sar::format($delivery).' (confirmed once you choose your area)'
            : 'Delivery: Free';

        return "Kumusta! I would like to order from Pinoy Food KSA:\n"
            .implode("\n", $rows)
            ."\n\nItems total: ".Sar::format($subtotal)
            ."\n".$deliveryLine
            ."\nTotal: ".Sar::format($total ?? $subtotal)
            ."\n\nMy name:\nMy area in Riyadh:\nLocation pin (optional):";
    }

    /** The message for an order that is already saved: it carries the order number so staff can match it. */
    public function placedOrderText(Order $order): string
    {
        $order->loadMissing('items', 'area');

        $rows = $order->items->map(fn ($i) => '- '.$i->qty.' x '.$i->product_name
            .' ('.Sar::format((string) $i->unit_price).' each) = '.Sar::format((string) $i->line_total))->all();

        $delivery = bccomp((string) $order->delivery_fee, '0', 2) === 1
            ? 'Delivery: '.Sar::format((string) $order->delivery_fee)
            : 'Delivery: Free';

        return "Kumusta! Here is my order {$order->order_no} from Pinoy Food KSA:\n"
            .implode("\n", $rows)
            ."\n\nItems total: ".Sar::format((string) $order->subtotal)
            ."\n".$delivery
            ."\nTotal, pay on delivery: ".Sar::format((string) $order->total)
            ."\n\nName: ".$order->customer_name
            ."\nArea: ".($order->area?->name ?? '-')
            ."\n\nPlease confirm my order. Salamat!";
    }

    public function orderStatusText(Order $order): string
    {
        $first = Str::before(trim((string) $order->customer_name), ' ') ?: 'kababayan';
        $no = (string) $order->order_no;
        $due = Sar::format((string) ($order->amount_due_on_delivery ?? '0'));
        $slot = filled($order->slot_label) ? (string) $order->slot_label : 'the agreed time';

        return match (OrderStatusService::current($order)) {
            'new' => "Kumusta {$first}! We received your order {$no}. We will confirm it shortly.",
            'confirmed' => "Hi {$first}, your order {$no} is confirmed. Delivery: {$slot}. Please have {$due} ready for the rider.",
            'out_for_delivery' => "Hi {$first}, your order {$no} is on the way. The rider will collect {$due}.",
            'delivered' => "Salamat, {$first}! Order {$no} was delivered. Enjoy your meal!",
            'no_answer' => "Hi {$first}, our rider could not reach you for order {$no}. Please reply with a good time, or send your location pin.",
            'cancelled' => "Hi {$first}, your order {$no} was cancelled. Message us if you would like to order again.",
            default => "Hi {$first}, this is Pinoy Food KSA about your order {$no}.",
        };
    }

    /** Every offer ends with the STOP line (spec 6.7). */
    public function offerText(string $body): string
    {
        $body = trim($body);

        if (! preg_match('/reply\s+stop/i', $body)) {
            $body .= "\nReply STOP to unsubscribe.";
        }

        return $body;
    }
}