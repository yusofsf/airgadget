<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.zarinpal.mock' => true]);

    $category = Category::create([
        'name' => 'لوازم جانبی',
        'slug' => 'accessories',
    ]);

    Product::create([
        'category_id' => $category->id,
        'name' => 'هدفون تست',
        'slug' => 'test-headphone',
        'sku' => 'TEST-001',
        'price' => 500000,
        'stock' => 3,
        'is_active' => true,
    ]);
});

test('a guest can pay with the mock zarinpal gateway and receives a trackable order', function () {
    $product = Product::firstOrFail();

    $response = $this->post(route('checkout.store'), [
        'first_name' => 'علی',
        'last_name' => 'احمدی',
        'phone' => '09121234567',
        'postal_code' => '1234567890',
        'address' => 'مشهد، خیابان نمونه، پلاک ۱۰',
        'shipping_method' => 'post',
        'payment_method' => 'zarinpal',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    $order = Order::firstOrFail();
    $response->assertRedirectContains("/payments/zarinpal/callback/{$order->id}/");
    expect($order->payment_authority)->toStartWith('MOCK-');
    expect($product->fresh()->stock)->toBe(1);

    $this->get(route('payments.zarinpal.callback', [
        'order' => $order,
        'token' => $order->invoice_token,
        'Status' => 'OK',
        'Authority' => $order->payment_authority,
    ]))->assertRedirect(route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token]));

    $order->refresh();
    expect($order->status)->toBe('pending_review')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_reference)->toStartWith('TEST-');

    $this->get(route('orders.track', ['number' => $order->number, 'phone' => '09121234567']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'tracking')
            ->where('trackedOrder.number', $order->number));

    $this->get(route('orders.invoice.pdf', ['order' => $order, 'token' => $order->invoice_token]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a cancelled payment releases reserved stock only once', function () {
    $product = Product::firstOrFail();
    $payload = [
        'first_name' => 'مینا',
        'last_name' => 'رضایی',
        'phone' => '09123334444',
        'postal_code' => '9876543210',
        'address' => 'مشهد، آدرس آزمایشی',
        'shipping_method' => 'pickup',
        'payment_method' => 'zarinpal',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ];

    $this->post(route('checkout.store'), $payload);
    $order = Order::firstOrFail();
    expect($product->fresh()->stock)->toBe(1);

    $callback = route('payments.zarinpal.callback', [
        'order' => $order,
        'token' => $order->invoice_token,
        'Status' => 'NOK',
        'Authority' => $order->payment_authority,
    ]);
    $this->get($callback)->assertOk();
    $this->get($callback)->assertOk();

    expect($product->fresh()->stock)->toBe(3)
        ->and($order->fresh()->inventory_released)->toBeTrue()
        ->and($order->fresh()->status)->toBe('failed');
});

test('a guest can submit an exact card to card payment amount and receipt', function () {
    Storage::fake('local');
    $product = Product::firstOrFail();

    $response = $this->post(route('checkout.store'), [
        'first_name' => 'سارا',
        'last_name' => 'محمدی',
        'phone' => '09125556666',
        'postal_code' => '1122334455',
        'address' => 'مشهد، خیابان نمونه',
        'shipping_method' => 'pickup',
        'payment_method' => 'card_to_card',
        'card_amount' => '500,000',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $order = Order::firstOrFail();
    $response->assertRedirect(route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token]));
    expect($order->status)->toBe('pending_review')
        ->and($order->payment_method)->toBe('card_to_card')
        ->and($order->card_to_card_amount)->toBe(500000)
        ->and($order->payment_receipt)->not->toBeNull();
    Storage::disk('local')->assertExists($order->payment_receipt);
});
