<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderReservationService
{
    public function expireUnpaidOrders(): int
    {
        $expired = 0;

        Order::query()
            ->where('status', 'pending_payment')
            ->whereNull('paid_at')
            ->where('inventory_released', false)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->pluck('id')
            ->each(function (int $orderId) use (&$expired) {
                DB::transaction(function () use ($orderId, &$expired) {
                    $order = Order::with('items')->lockForUpdate()->find($orderId);
                    if (! $order || $order->paid_at || $order->inventory_released || $order->payment_expires_at?->isFuture()) {
                        return;
                    }

                    foreach ($order->items as $item) {
                        if ($item->product_id) {
                            Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                        }
                    }

                    $order->update([
                        'status' => 'unpaid',
                        'inventory_released' => true,
                    ]);
                    $expired++;
                });
            });

        return $expired;
    }
}
