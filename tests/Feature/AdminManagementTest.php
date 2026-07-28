<?php

use App\Models\Article;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can browse order summaries and open complete order details', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $customer = User::factory()->create();
    $order = Order::create([
        'user_id' => $customer->id,
        'number' => 'AG-TEST-1001',
        'status' => 'processing',
        'shipping_method' => 'post',
        'payment_method' => 'zarinpal',
        'subtotal' => 900000,
        'discount' => 0,
        'shipping_cost' => 100000,
        'tax' => 0,
        'total' => 1000000,
        'address' => [
            'customer_name' => 'مشتری تست',
            'phone' => '09120000000',
            'postal_code' => '1234567890',
            'full' => 'مشهد، آدرس تست',
        ],
    ]);
    $order->items()->create([
        'name' => 'محصول تست',
        'sku' => 'TEST-1',
        'price' => 900000,
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'admin-orders')
            ->where('orders.data.0.number', 'AG-TEST-1001')
        );

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'admin-order')
            ->where('adminOrder.number', 'AG-TEST-1001')
            ->where('adminOrder.items.0.name', 'محصول تست')
            ->where('adminOrder.address.postal_code', '1234567890')
        );
});

test('non admins cannot access order management', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.orders.index'))->assertForbidden();
});

test('article can be created without a topic or meta keywords', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.articles.store'), [
            'title' => 'مقاله بدون موضوع',
            'body' => 'متن کامل مقاله آزمایشی',
            'excerpt' => 'خلاصه مقاله',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin'));

    $article = Article::where('title', 'مقاله بدون موضوع')->firstOrFail();

    expect($article->topic)->toBeNull()
        ->and($article->meta_keywords)->toBeNull();
});

test('a product cannot be created without its main price', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'محصول بدون قیمت',
        ])
        ->assertSessionHasErrors('price');

    $this->assertDatabaseMissing('products', ['name' => 'محصول بدون قیمت']);
});
