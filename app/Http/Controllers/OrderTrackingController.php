<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Phone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $trackedOrder = null;
        $trackingError = null;

        if ($request->filled('number') || $request->filled('phone')) {
            $validated = $request->validate([
                'number' => ['required', 'string', 'max:30'],
                'phone' => ['required', 'string', 'max:20'],
            ]);
            $phone = Phone::normalize($validated['phone']);
            $order = Order::with('items')
                ->where('number', strtoupper(trim($validated['number'])))
                ->first();

            if ($order && hash_equals((string) data_get($order->address, 'phone'), $phone)) {
                $trackedOrder = $order;
            } else {
                $trackingError = 'سفارشی با این کد و شماره موبایل پیدا نشد.';
            }
        }

        return Inertia::render('Storefront', [
            'view' => 'tracking',
            'trackedOrder' => $trackedOrder,
            'trackingError' => $trackingError,
        ]);
    }
}
