<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('storefront is declared as Persian and right to left', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="fa" dir="rtl">', false);
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

test('admin can open the complete product editor and upload a visible main image', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $category = Category::create(['name' => 'لوازم جانبی', 'slug' => 'accessories']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'محصول قابل ویرایش',
        'slug' => 'editable-product',
        'sku' => 'EDIT-100',
        'price' => 500000,
        'stock' => 3,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'admin-product')
            ->where('adminProduct.name', 'محصول قابل ویرایش')
            ->has('categories', 1)
        );

    $this->actingAs($admin)
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => 'محصول ویرایش‌شده',
            'sku' => 'EDIT-100',
            'category_id' => $category->id,
            'price' => 650000,
            'stock' => 5,
            'is_active' => true,
            'main_image_choice' => 'new:0',
            'images' => [UploadedFile::fake()->image('product.jpg', 600, 600)],
        ])
        ->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->name)->toBe('محصول ویرایش‌شده')
        ->and($product->main_image)->toStartWith('/product-images/')
        ->and($product->images)->toHaveCount(1);

    $storedPath = 'products/'.basename($product->main_image);
    Storage::disk('public')->assertExists($storedPath);
    $this->get($product->main_image)
        ->assertOk()
        ->assertHeader('cache-control', 'immutable, max-age=31536000, public');

    $oldImageId = $product->images->first()->id;
    $this->actingAs($admin)
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => 'محصول ویرایش‌شده',
            'sku' => 'EDIT-100',
            'category_id' => $category->id,
            'price' => 650000,
            'stock' => 5,
            'is_active' => true,
            'remove_image_ids' => [$oldImageId],
            'main_image_choice' => 'new:0',
            'images' => [UploadedFile::fake()->image('replacement.webp', 800, 800)],
        ])
        ->assertSessionHasNoErrors();

    $product->refresh()->load('images');
    $replacementPath = 'products/'.basename($product->main_image);
    expect($product->images)->toHaveCount(1)
        ->and($product->main_image)->not->toBe('/product-images/'.basename($storedPath));
    Storage::disk('public')->assertMissing($storedPath);
    Storage::disk('public')->assertExists($replacementPath);

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin'));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    Storage::disk('public')->assertMissing($replacementPath);
});
