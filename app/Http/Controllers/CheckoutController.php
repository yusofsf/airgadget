<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\ZarinpalGateway;
use App\Services\OrderReservationService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function store(Request $request, OrderReservationService $reservations): SymfonyResponse
    {
        if (! $request->user()?->hasCompleteShippingAddress()) {
            return redirect()
                ->route('account', ['complete_profile' => 1])
                ->with('status', 'برای ثبت سفارش، ابتدا آدرس و کدپستی را در حساب کاربری وارد کنید.');
        }

        $reservations->expireUnpaidOrders();
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'postal_code' => $this->englishDigits($request->input('postal_code')),
            'card_amount' => str_replace([',', '٬', ' '], '', $this->englishDigits($request->input('card_amount'))),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'postal_code' => ['required', 'regex:/^\d{10}$/'],
            'shipping_method' => ['required', 'in:mashhad_courier,pickup,post'],
            'payment_method' => ['required', 'in:card_to_card'],
            'card_amount' => ['required', 'integer', 'min:1'],
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'address' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.selected_color' => ['nullable', 'string', 'max:100'],
        ]);

        $shipping = collect(StoreSetting::shippingMethods())->first(
            fn ($method) => $method['code'] === $validated['shipping_method'] && $method['is_active']
        );
        if (! $shipping) {
            throw ValidationException::withMessages(['shipping_method' => 'روش ارسال انتخاب‌شده فعال نیست.']);
        }

        $order = DB::transaction(function () use ($request, $validated, $shipping) {
            $itemLines = collect($validated['items'])
                ->map(function ($item) {
                    $item['selected_color'] = trim((string) ($item['selected_color'] ?? '')) ?: null;

                    return $item;
                })
                ->groupBy(fn ($item) => $item['product_id'].'|'.($item['selected_color'] ?? ''))
                ->map(function ($items) {
                    $line = $items->first();
                    $line['quantity'] = $items->sum('quantity');

                    return $line;
                })
                ->values();
            $requestedItems = $itemLines
                ->groupBy('product_id')
                ->map(fn ($items) => $items->sum('quantity'));
            $products = Product::whereIn('id', $requestedItems->keys())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $subtotal = 0;

            foreach ($requestedItems as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $product->stock < $quantity) {
                    throw ValidationException::withMessages(['items' => 'موجودی یکی از محصولات سبد کافی نیست.']);
                }
                $subtotal += (int) ($product->sale_price ?: $product->price) * $quantity;
            }

            foreach ($itemLines as $line) {
                $product = $products->get($line['product_id']);
                $availableColors = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/[,،|\/]+/u', (string) data_get($product?->attributes, 'color', '')) ?: []
                )));
                if ($availableColors && ! in_array($line['selected_color'], $availableColors, true)) {
                    throw ValidationException::withMessages(['items' => "رنگ انتخاب‌شده برای محصول {$product->name} معتبر نیست."]);
                }
            }

            $shippingCost = (int) $shipping['cost'];
            $total = $subtotal + $shippingCost;
            if ((int) $validated['card_amount'] !== $total) {
                throw ValidationException::withMessages(['card_amount' => 'مبلغ واریزی باید دقیقاً با جمع کل سفارش برابر باشد.']);
            }
            $receiptPath = $request->file('receipt')
                ? $request->file('receipt')->store('payment-receipts', 'local')
                : null;
            $order = Order::create([
                'user_id' => $request->user()?->id,
                'number' => 'AG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'invoice_token' => Str::random(48),
                'status' => 'pending_review',
                'shipping_method' => $validated['shipping_method'],
                'payment_method' => $validated['payment_method'],
                'payment_expires_at' => null,
                'payment_receipt' => $receiptPath,
                'card_to_card_amount' => (int) $validated['card_amount'],
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

            foreach ($itemLines as $line) {
                $product = $products->get($line['product_id']);
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->sale_price ?: $product->price,
                    'quantity' => $line['quantity'],
                    'selected_color' => $line['selected_color'],
                ]);
            }
            foreach ($requestedItems as $productId => $quantity) {
                $products->get($productId)->decrement('stock', $quantity);
            }

            return $order;
        });

        return redirect()
            ->route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token])
            ->with('status', "فیش شما ثبت شد. کد سفارش {$order->number} است و پس از بررسی مدیر تأیید می‌شود.");
    }

    public function callback(Request $request, Order $order, string $token, ZarinpalGateway $gateway, OrderReservationService $reservations): Response|RedirectResponse
    {
        $this->authorizeToken($order, $token);
        $reservations->expireUnpaidOrders();
        $order->refresh();
        $authority = (string) $request->query('Authority');

        if ($order->paid_at) {
            return redirect()->route('orders.invoice', ['order' => $order, 'token' => $token]);
        }

        if ($order->status === 'unpaid' || $order->inventory_released) {
            return Inertia::render('Storefront', [
                'view' => 'payment-result',
                'paymentResult' => ['success' => false, 'number' => $order->number, 'message' => 'مهلت ۱۰ دقیقه‌ای پرداخت تمام شده و سفارش پرداخت‌نشده ثبت شده است.'],
            ]);
        }

        if ($request->query('Status') !== 'OK' || $authority === '' || ! hash_equals((string) $order->payment_authority, $authority)) {
            return Inertia::render('Storefront', [
                'view' => 'payment-result',
                'paymentResult' => [
                    'success' => false,
                    'number' => $order->number,
                    'expires_at' => optional($order->payment_expires_at)->toIso8601String(),
                    'message' => 'پرداخت انجام نشد. سبد و موجودی شما تا پایان مهلت ۱۰ دقیقه‌ای محفوظ است و می‌توانید دوباره پرداخت کنید.',
                ],
            ]);
        }

        $verification = $gateway->verify($authority, (int) $order->total);
        if (! $verification['success']) {
            return Inertia::render('Storefront', [
                'view' => 'payment-result',
                'paymentResult' => [
                    'success' => false,
                    'number' => $order->number,
                    'expires_at' => optional($order->payment_expires_at)->toIso8601String(),
                    'message' => $verification['message'].' سبد شما تا پایان مهلت ۱۰ دقیقه‌ای حفظ می‌شود.',
                ],
            ]);
        }

        DB::transaction(function () use ($order, $verification) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            if (! $lockedOrder->paid_at) {
                $lockedOrder->update([
                    'status' => 'pending_review',
                    'payment_reference' => $verification['reference'],
                    'paid_at' => now(),
                    'payment_expires_at' => null,
                ]);
            }
        });

        return redirect()
            ->route('orders.invoice', ['order' => $order, 'token' => $token])
            ->with('status', "پرداخت موفق بود. کد سفارش شما {$order->number} است.");
    }

    public function invoice(Order $order, string $token): Response
    {
        $this->authorizeToken($order, $token);

        return Inertia::render('Storefront', [
            'view' => 'invoice',
            'invoice' => $order->load('items'),
            'invoiceShipping' => collect(StoreSetting::shippingMethods())->firstWhere('code', $order->shipping_method),
            'invoiceSender' => $this->invoiceSender(),
        ]);
    }

    public function invoicePdf(Order $order, string $token): SymfonyResponse
    {
        $this->authorizeToken($order, $token);
        $order->load('items');
        $shipping = collect(StoreSetting::shippingMethods())->firstWhere('code', $order->shipping_method);
        $sender = $this->invoiceSender();
        $html = view('pdf.invoice', compact('order', 'shipping', 'sender'))->render();
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'margin_top' => 12,
            'margin_right' => 12,
            'margin_bottom' => 12,
            'margin_left' => 12,
        ]);
        $pdf->SetDirectionality('rtl');
        $pdf->WriteHTML($html);

        return response($pdf->Output("invoice-{$order->number}.pdf", Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"invoice-{$order->number}.pdf\"",
        ]);
    }

    private function failAndRelease(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->inventory_released || $lockedOrder->paid_at) {
                return;
            }
            foreach ($lockedOrder->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }
            $lockedOrder->update(['status' => 'unpaid', 'inventory_released' => true]);
        });
    }

    private function authorizeToken(Order $order, string $token): void
    {
        abort_unless($order->invoice_token && hash_equals($order->invoice_token, $token), 404);
    }

    private function invoiceSender(): ?User
    {
        return User::query()
            ->where('is_admin', true)
            ->oldest('id')
            ->first(['id', 'first_name', 'last_name', 'phone_number', 'postal_code', 'address']);
    }

    private function englishDigits(mixed $value): string
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
