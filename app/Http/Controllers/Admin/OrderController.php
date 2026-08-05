<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-orders',
            'orders' => Order::withCount('items')
                ->withSum('items', 'quantity')
                ->latest()
                ->paginate(25),
            'orderProducts' => Product::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'sale_price', 'stock']),
            'orderUsers' => User::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'phone_number', 'postal_code', 'address', 'email']),
            'orderShippingMethods' => collect(StoreSetting::shippingMethods())
                ->where('is_active', true)
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'postal_code' => strtr((string) $request->input('postal_code'), [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]),
        ]);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'postal_code' => ['required', 'regex:/^\d{10}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'shipping_method' => ['required', 'in:mashhad_courier,pickup,post'],
            'status' => ['required', 'in:pending_review,processing,completed'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $shipping = collect(StoreSetting::shippingMethods())->first(
            fn (array $method) => $method['code'] === $validated['shipping_method'] && $method['is_active']
        );
        if (! $shipping) {
            throw ValidationException::withMessages(['shipping_method' => 'روش ارسال انتخاب‌شده فعال نیست.']);
        }

        $order = DB::transaction(function () use ($validated, $shipping) {
            $requestedItems = collect($validated['items'])->keyBy('product_id');
            $products = Product::query()
                ->whereIn('id', $requestedItems->keys())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            foreach ($requestedItems as $productId => $item) {
                $product = $products->get($productId);
                if (! $product || $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'موجودی یکی از محصولات برای ثبت سفارش کافی نیست.']);
                }
                $subtotal += (int) ($product->sale_price ?: $product->price) * $item['quantity'];
            }

            $shippingCost = (int) $shipping['cost'];
            $total = $subtotal + $shippingCost;
            $paid = in_array($validated['status'], ['processing', 'completed'], true);
            $order = Order::create([
                'user_id' => $validated['user_id'] ?? null,
                'number' => 'AG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'invoice_token' => Str::random(48),
                'status' => $validated['status'],
                'shipping_method' => $validated['shipping_method'],
                'payment_method' => 'card_to_card',
                'card_to_card_amount' => $paid ? $total : null,
                'paid_at' => $paid ? now() : null,
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_cost' => $shippingCost,
                'tax' => 0,
                'total' => $total,
                'address' => [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'customer_name' => trim($validated['first_name'].' '.$validated['last_name']),
                    'phone' => $validated['phone'],
                    'postal_code' => $validated['postal_code'],
                    'full' => $validated['address'],
                ],
            ]);

            foreach ($requestedItems as $productId => $item) {
                $product = $products->get($productId);
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->sale_price ?: $product->price,
                    'quantity' => $item['quantity'],
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', "سفارش {$order->number} با موفقیت ایجاد شد و فاکتور آن آماده است.");
    }

    public function show(Order $order): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-order',
            'adminOrder' => $order->load(['items', 'user']),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_payment,processing,pending_review,completed,cancelled,failed,refunded,unpaid'],
        ]);

        DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            if (in_array($validated['status'], ['cancelled', 'refunded'], true) && ! $lockedOrder->inventory_released) {
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        Product::whereKey($item->product_id)->increment('stock', $item->quantity);
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
