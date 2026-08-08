<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
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

function checkoutUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'phone_number' => '09123334444',
        'postal_code' => '9876543210',
        'address' => 'مشهد، آدرس آزمایشی',
    ], $attributes));
}

test('guests must log in before opening checkout', function () {
    $this->get(route('checkout'))->assertRedirect(route('login'));
});

test('the cart page is available before checkout', function () {
    $this->get(route('cart'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront')->where('view', 'cart'));
});

test('a user without address and postal code is sent to account profile', function () {
    $user = checkoutUser(['postal_code' => null, 'address' => null]);

    $this->actingAs($user)
        ->get(route('checkout'))
        ->assertRedirect(route('account', ['complete_profile' => 1]))
        ->assertSessionHas('status', 'برای ثبت سفارش، ابتدا آدرس و کدپستی را در حساب کاربری وارد کنید.');

    $this->actingAs($user)
        ->post(route('checkout.store'))
        ->assertRedirect(route('account', ['complete_profile' => 1]));

    expect(Order::count())->toBe(0);
});

test('online payment is rejected because card to card is the only payment method', function () {
    $product = Product::firstOrFail();
    $user = checkoutUser();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
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

test('an authenticated user with a complete profile can submit an exact card payment and receipt', function () {
    Storage::fake('local');
    $product = Product::firstOrFail();
    $user = checkoutUser();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $user->phone_number,
        'postal_code' => $user->postal_code,
        'address' => $user->address,
        'shipping_method' => 'pickup',
        'payment_method' => 'card_to_card',
        'card_amount' => '500,000',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $order = Order::firstOrFail();
    $response->assertRedirect(route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token]));
    expect($order->user_id)->toBe($user->id)
        ->and($order->status)->toBe('pending_review')
        ->and($order->payment_method)->toBe('card_to_card')
        ->and($order->card_to_card_amount)->toBe(500000)
        ->and($order->payment_receipt)->not->toBeNull();
    Storage::disk('local')->assertExists($order->payment_receipt);
});

test('the selected product color is validated and stored on the order item', function () {
    Storage::fake('local');
    $product = Product::firstOrFail();
    $product->update(['attributes' => ['color' => 'مشکی، سفید، آبی']]);
    $user = checkoutUser();

    $payload = [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $user->phone_number,
        'postal_code' => $user->postal_code,
        'address' => $user->address,
        'shipping_method' => 'pickup',
        'payment_method' => 'card_to_card',
        'card_amount' => '500000',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'selected_color' => 'سفید']],
    ];

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

    expect(Order::firstOrFail()->items()->firstOrFail()->selected_color)->toBe('سفید');
});
