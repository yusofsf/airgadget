<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_payment,processing,pending_review,completed,cancelled,failed,refunded'],
        ]);

        DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            if (in_array($validated['status'], ['cancelled', 'refunded'], true) && ! $lockedOrder->inventory_released) {
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        \App\Models\Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                    }
                }
                $lockedOrder->inventory_released = true;
            }
            $lockedOrder->status = $validated['status'];
            if ($lockedOrder->payment_method === 'card_to_card' && in_array($validated['status'], ['processing', 'completed'], true) && ! $lockedOrder->paid_at) {
                $lockedOrder->paid_at = now();
            }
            $lockedOrder->save();
        });

        return back()->with('status', "وضعیت سفارش {$order->number} به‌روز شد.");
    }

    public function receipt(Order $order): BinaryFileResponse
    {
        abort_unless($order->payment_method === 'card_to_card' && $order->payment_receipt, 404);
        abort_unless(Storage::disk('local')->exists($order->payment_receipt), 404);

        return response()->file(Storage::disk('local')->path($order->payment_receipt));
    }
}
