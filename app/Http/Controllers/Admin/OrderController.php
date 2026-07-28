<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_payment,processing,pending_review,completed,cancelled,failed,refunded'],
        ]);

        $order->update($validated);

        return back()->with('status', "وضعیت سفارش {$order->number} به‌روز شد.");
    }
}
