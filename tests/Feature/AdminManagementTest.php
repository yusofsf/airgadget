<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

test('admin can create an order and download its invoice pdf', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'first_name' => 'مدیر',
        'last_name' => 'فروشگاه',
        'phone_number' => '09205850190',
        'postal_code' => '9187654321',
        'address' => 'مشهد، عبدالمطلب ۳۵',
    ]);
    $customer = User::factory()->create([
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'phone_number' => '09120000000',
        'postal_code' => '1234567890',
        'address' => 'مشهد، خیابان آزمون',
    ]);
    $category = Category::create(['name' => 'لوازم جانبی', 'slug' => 'admin-order-accessories']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'شارژر تست',
        'slug' => 'admin-order-charger',
        'sku' => 'ADMIN-ORDER-1',
        'price' => 400000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'user_id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone_number,
            'postal_code' => $customer->postal_code,
            'address' => $customer->address,
            'shipping_method' => 'pickup',
            'status' => 'processing',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors();

    $order = Order::latest('id')->firstOrFail();
    expect($order->user_id)->toBe($customer->id)
        ->and($order->subtotal)->toEqual(800000)
        ->and($order->total)->toEqual(800000)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($product->fresh()->stock)->toBe(3);

    $this->get(route('orders.invoice', ['order' => $order, 'token' => $order->invoice_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceSender.first_name', 'مدیر')
            ->where('invoiceSender.last_name', 'فروشگاه')
            ->where('invoiceSender.phone_number', '09205850190')
            ->where('invoiceSender.postal_code', '9187654321')
            ->where('invoiceSender.address', 'مشهد، عبدالمطلب ۳۵')
        );

    $pdf = $this->get(route('orders.invoice.pdf', ['order' => $order, 'token' => $order->invoice_token]));
    $pdf->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', "attachment; filename=\"invoice-{$order->number}.pdf\"");
    expect($pdf->getContent())->toStartWith('%PDF-');
    expect(storage_path('app/mpdf'))->toBeDirectory();
});

test('admin can see registered users and non admins cannot access the list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $customer = User::factory()->create([
        'first_name' => 'کاربر',
        'last_name' => 'آزمایشی',
        'phone_number' => '09120000000',
        'email' => 'customer@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'admin-users')
            ->where('users.total', 2)
            ->where('users.data.0.id', $customer->id)
            ->where('users.data.0.phone_number', '09120000000')
        );

    $this->actingAs($customer)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admin can move a registered order to confirmed and then sent', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $order = Order::create([
        'number' => 'AG-STATUS-1001',
        'status' => 'pending_review',
        'shipping_method' => 'post',
        'payment_method' => 'card_to_card',
        'subtotal' => 500000,
        'discount' => 0,
        'shipping_cost' => 0,
        'tax' => 0,
        'total' => 500000,
        'address' => ['customer_name' => 'مشتری تست', 'phone' => '09120000000'],
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $order), ['status' => 'processing'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe('processing')
        ->and($order->fresh()->paid_at)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $order), ['status' => 'completed'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe('completed');
});

test('storefront is declared as Persian and right to left', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="fa" dir="rtl">', false);
});

test('admin can maintain unique content and seo fields for a product category', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = Category::create(['name' => 'کابل', 'slug' => 'cables']);

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $category), [
            'name' => 'کابل و مبدل',
            'description' => 'راهنمای انتخاب کابل بر اساس نوع درگاه، توان و طول کابل.',
            'meta_title' => 'خرید کابل و مبدل موبایل',
            'meta_description' => 'انواع کابل شارژ و تبدیل را مقایسه و انتخاب کنید.',
            'meta_keywords' => 'کابل شارژ, مبدل موبایل',
        ])
        ->assertSessionHasNoErrors();

    expect($category->fresh())
        ->name->toBe('کابل و مبدل')
        ->slug->toBe('cables')
        ->description->toContain('راهنمای انتخاب کابل')
        ->meta_title->toBe('خرید کابل و مبدل موبایل');
});

test('article can be created without a topic or meta keywords', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.articles.store'), [
            'title' => 'مقاله بدون موضوع',
            'body' => 'متن کامل مقاله آزمایشی',
            'excerpt' => 'خلاصه مقاله',
            'main_image' => UploadedFile::fake()->image('article.jpg', 800, 500),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin'));

    $article = Article::where('title', 'مقاله بدون موضوع')->firstOrFail();

    expect($article->topic)->toBeNull()
        ->and($article->meta_keywords)->toBeNull()
        ->and($article->image)->toStartWith('/storage/articles/');
    Storage::disk('public')->assertExists(Str::after($article->image, '/storage/'));
});

test('product and article taxonomies are stored and can be edited or deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'هندزفری تگ‌دار',
            'category_name' => 'صوتی',
            'price' => 900000,
            'tags' => 'انکر، هندزفری',
            'color' => 'مشکی',
            'specifications' => [
                ['key' => 'نوع اتصال', 'value' => 'بلوتوث'],
                ['key' => 'نسخه بلوتوث', 'value' => '۵.۳'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $product = Product::where('name', 'هندزفری تگ‌دار')->firstOrFail()->load(['tags', 'specifications']);
    expect($product->tags->pluck('name')->all())->toEqualCanonicalizing(['انکر', 'هندزفری'])
        ->and($product->attributes['color'])->toBe('مشکی')
        ->and($product->specifications->pluck('key')->all())->toBe(['نوع اتصال', 'نسخه بلوتوث']);

    $this->actingAs($admin)
        ->post(route('admin.articles.store'), [
            'title' => 'راهنمای هندزفری',
            'body' => 'متن کامل راهنما',
            'category_name' => 'راهنمای خرید',
            'tags' => 'هندزفری، آموزش',
        ])
        ->assertSessionHasNoErrors();

    $article = Article::where('title', 'راهنمای هندزفری')->firstOrFail()->load(['tags', 'category']);
    expect($article->category?->name)->toBe('راهنمای خرید')
        ->and($article->tags->pluck('name')->all())->toEqualCanonicalizing(['هندزفری', 'آموزش']);

    $tag = Tag::where('name', 'آموزش')->firstOrFail();
    $this->actingAs($admin)
        ->patch(route('admin.tags.update', $tag), ['name' => 'آموزش تخصصی'])
        ->assertSessionHasNoErrors();
    expect($tag->refresh()->name)->toBe('آموزش تخصصی');

    $category = ArticleCategory::where('name', 'راهنمای خرید')->firstOrFail();
    $this->actingAs($admin)
        ->patch(route('admin.article-categories.update', $category), [
            'name' => 'راهنمای انتخاب',
            'description' => 'راهنمای انتخاب محصولات',
        ])
        ->assertSessionHasNoErrors();
    expect($category->refresh()->name)->toBe('راهنمای انتخاب');

    $this->actingAs($admin)->delete(route('admin.tags.destroy', $tag))->assertSessionHasNoErrors();
    $this->actingAs($admin)->delete(route('admin.article-categories.destroy', $category))->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('article_categories', ['id' => $category->id]);
    expect($article->refresh()->article_category_id)->toBeNull();
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
    Storage::fake('product_images');
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
        ->post(route('admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('separate-upload.jpg', 600, 600),
            'is_main' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('main_image', fn (string $path) => str_starts_with($path, '/product-images/'));

    $product->refresh()->load('images');
    expect($product->images)->toHaveCount(1)
        ->and($product->main_image)->toStartWith('/product-images/');

    $separateUploadPath = 'products/'.basename($product->main_image);
    Storage::disk('product_images')->assertExists(basename($separateUploadPath));

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => 'محصول قابل ویرایش',
            'sku' => 'EDIT-100',
            'category_id' => $category->id,
            'price' => 500000,
            'stock' => 3,
            'is_active' => true,
            'expected_image_count' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images');

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => 'محصول ویرایش‌شده',
            'sku' => 'EDIT-100',
            'category_id' => $category->id,
            'price' => 650000,
            'stock' => 5,
            'is_active' => true,
            'color' => 'سفید',
            'specifications' => [
                ['key' => 'عمر باتری', 'value' => '۸ ساعت'],
            ],
            'main_image_choice' => 'new:0',
            'expected_image_count' => 1,
            'images' => [UploadedFile::fake()->image('product.jpg', 600, 600)],
        ])
        ->assertOk()
        ->assertJsonPath('uploaded_images', 1);

    $product->refresh()->load('specifications');
    expect($product->name)->toBe('محصول ویرایش‌شده')
        ->and($product->main_image)->toStartWith('/product-images/')
        ->and($product->images)->toHaveCount(2)
        ->and($product->attributes['color'])->toBe('سفید')
        ->and($product->specifications->first()->value)->toBe('۸ ساعت');

    $storedPath = 'products/'.basename($product->main_image);
    Storage::disk('product_images')->assertExists(basename($storedPath));
    $this->get($product->main_image)
        ->assertOk()
        ->assertHeader('cache-control', 'immutable, max-age=31536000, public');

    $oldImageIds = $product->images->pluck('id')->all();
    $this->actingAs($admin)
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => 'محصول ویرایش‌شده',
            'sku' => 'EDIT-100',
            'category_id' => $category->id,
            'price' => 650000,
            'stock' => 5,
            'is_active' => true,
            'remove_image_ids' => $oldImageIds,
            'main_image_choice' => 'new:0',
            'images' => [UploadedFile::fake()->image('replacement.webp', 800, 800)],
        ])
        ->assertSessionHasNoErrors();

    $product->refresh()->load('images');
    $replacementPath = 'products/'.basename($product->main_image);
    expect($product->images)->toHaveCount(1)
        ->and($product->main_image)->not->toBe('/product-images/'.basename($storedPath));
    Storage::disk('product_images')->assertMissing(basename($separateUploadPath));
    Storage::disk('product_images')->assertMissing(basename($storedPath));
    Storage::disk('product_images')->assertExists(basename($replacementPath));

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin'));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    Storage::disk('product_images')->assertMissing(basename($replacementPath));
});

test('removing product images deletes modern and legacy files from host storage', function () {
    Storage::fake('public');
    Storage::fake('product_images');
    Storage::fake('legacy_product_images');

    $admin = User::factory()->create(['is_admin' => true]);
    $category = Category::create(['name' => 'Images', 'slug' => 'images']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Image cleanup product',
        'slug' => 'image-cleanup-product',
        'sku' => 'IMAGE-CLEANUP-1',
        'price' => 100000,
        'stock' => 1,
        'is_active' => true,
        'main_image' => '/product-images/current.jpg',
        'gallery' => ['/product-images/current.jpg', '/storage/products/legacy.jpg'],
    ]);
    $image = $product->images()->create(['path' => '/product-images/current.jpg', 'sort_order' => 1]);

    Storage::disk('product_images')->put('current.jpg', 'current');
    Storage::disk('legacy_product_images')->put('legacy.jpg', 'legacy');

    $this->actingAs($admin)
        ->post(route('admin.products.update', $product), [
            '_method' => 'patch',
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $category->id,
            'price' => $product->price,
            'stock' => $product->stock,
            'is_active' => true,
            'remove_image_ids' => [$image->id],
            'remove_legacy_paths' => ['/storage/products/legacy.jpg'],
            'expected_image_count' => 0,
        ])
        ->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->main_image)->toBeNull()
        ->and($product->gallery)->toBe([]);
    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    Storage::disk('product_images')->assertMissing('current.jpg');
    Storage::disk('legacy_product_images')->assertMissing('legacy.jpg');
});
