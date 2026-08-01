<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('online payment is rejected because card to card is the only payment method', function () {
    $product = Product::firstOrFail();
    $response = $this->post(route('checkout.store'), [
        'first_name' => 'مینا',
        'last_name' => 'رضایی',
        'phone' => '09123334444',
        'postal_code' => '9876543210',
        'address' => 'مشهد، آدرس آزمایشی',
        'shipping_method' => 'post',
        'payment_method' => 'zarinpal',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $response->assertSessionHasErrors('payment_method');
    expect(Order::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(3);
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
