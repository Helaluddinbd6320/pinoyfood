<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Order;

class OrderDoneController extends Controller
{
    public function __invoke(string $orderNo)
    {
        $order = Order::query()->where('order_no', $orderNo)->with('items')->firstOrFail();
        $area = $order->area_id ? Area::query()->find($order->area_id) : null;

        return view('storefront.order-done', compact('order', 'area'));
    }
}