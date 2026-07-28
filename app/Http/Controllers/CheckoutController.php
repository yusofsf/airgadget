<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'postal_code' => strtr((string) $request->input('postal_code'), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']),
        ]);
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'postal_code' => ['required', 'regex:/^\d{10}$/'],
            'shipping_method' => ['required', 'in:mashhad_courier,pickup,post'],
            'payment_method' => ['required', 'in:zarinpal,card_to_card'],
            'address' => ['required', 'string', 'max:1000'],
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
                'user_id' => $request->user()?->id,
                'number' => 'AG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'invoice_token' => Str::random(48),
                'status' => $validated['payment_method'] === 'card_to_card' ? 'pending_review' : 'pending_payment',
                'shipping_method' => $validated['shipping_method'],
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_cost' => $shippingCost,
                'tax' => 0,
                'total' => $subtotal + $shippingCost,
                'address' => ['customer_name' => $validated['customer_name'], 'phone' => $validated['phone'], 'postal_code' => $validated['postal_code'], 'full' => $validated['address']],
            ]);

            foreach ($requestedItems as $productId => $quantity) {
                $product = $products->get($productId);
                $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => $product->sale_price ?: $product->price, 'quantity' => $quantity]);
            }

            return $order;
        });

        return redirect()->route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token]);
    }

    public function invoice(Order $order, string $token): Response
    {
        abort_unless($order->invoice_token && hash_equals($order->invoice_token, $token), 404);
        $method = collect(StoreSetting::shippingMethods())->firstWhere('code', $order->shipping_method);

        return Inertia::render('Storefront', ['view' => 'invoice', 'invoice' => $order->load('items'), 'invoiceShipping' => $method]);
    }
}
