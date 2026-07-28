<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipping_method' => ['required', 'in:mashhad_courier,pickup,post'],
            'payment_method' => ['required', 'in:zarinpal,card_to_card'],
            'address' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $shipping = collect(StoreSetting::shippingMethods())->first(
            fn ($method) => $method['code'] === $validated['shipping_method'] && $method['is_active']
        );
        if (! $shipping) {
            throw ValidationException::withMessages(['shipping_method' => 'روش ارسال انتخاب‌شده فعال نیست.']);
        }
        if ($validated['shipping_method'] !== 'pickup' && blank($validated['address'])) {
            throw ValidationException::withMessages(['address' => 'برای این روش ارسال، آدرس کامل لازم است.']);
        }

        $order = DB::transaction(function () use ($request, $validated, $shipping) {
            $requestedItems = collect($validated['items'])->groupBy('product_id')->map(fn ($items) => $items->sum('quantity'));
            $products = Product::whereIn('id', $requestedItems->keys())->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0;

            foreach ($requestedItems as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $product->stock < $quantity) {
                    throw ValidationException::withMessages(['items' => 'موجودی یکی از محصولات سبد کافی نیست.']);
                }
                $subtotal += (int) ($product->sale_price ?: $product->price) * $quantity;
            }

            $shippingCost = (int) $shipping['cost'];
            $order = Order::create([
                'user_id' => $request->user()->id,
                'number' => 'AG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'status' => $validated['payment_method'] === 'card_to_card' ? 'pending_review' : 'pending_payment',
                'shipping_method' => $validated['shipping_method'],
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_cost' => $shippingCost,
                'tax' => 0,
                'total' => $subtotal + $shippingCost,
                'address' => ['full' => $validated['shipping_method'] === 'pickup' ? 'دریافت حضوری از عبدالمطلب ۳۵' : $validated['address']],
            ]);

            foreach ($requestedItems as $productId => $quantity) {
                $product = $products->get($productId);
                $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => $product->sale_price ?: $product->price, 'quantity' => $quantity]);
            }

            return $order;
        });

        return redirect()->route('account')->with('status', "سفارش {$order->number} ثبت شد و در انتظار تأیید پرداخت است.");
    }
}
