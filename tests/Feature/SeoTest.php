<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;

test('public pages expose one stable canonical in html and headers', function () {
    config()->set('seo.canonical_url', 'https://airgadget.ir');

    $response = $this->get('http://www.airgadget.ir/shop?utm_source=google');

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'index,follow')
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/shop">', false);

    expect(substr_count($response->getContent(), 'rel="canonical"'))->toBe(1);
});

test('pagination is self canonical while shop filters are not indexable', function () {
    config()->set('seo.canonical_url', 'https://airgadget.ir');

    $this->get('/shop?page=2')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'index,follow')
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/shop?page=2">', false);

    $this->get('/shop?brand_id=1&min_price=100')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex,follow')
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/shop">', false)
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    $this->get('/shop?status=sale')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex,follow')
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/shop">', false);
});

test('product category pagination is self canonical and exposes category seo content', function () {
    config()->set('seo.canonical_url', 'https://airgadget.ir');
    $category = Category::create([
        'name' => 'Chargers',
        'slug' => 'chargers',
        'description' => 'A useful category buying guide.',
        'meta_title' => 'Buy Chargers',
        'meta_description' => 'Compare charger models.',
    ]);

    $this->get('/categories/chargers?page=2')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'index,follow')
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/categories/chargers?page=2">', false)
        ->assertInertia(fn ($page) => $page
            ->component('Storefront')
            ->where('selectedCategory.description', 'A useful category buying guide.')
            ->where('selectedCategory.meta_title', 'Buy Chargers')
            ->where('selectedCategory.meta_description', 'Compare charger models.'));
});

test('product canonical defaults to its route and supports an explicit canonical', function () {
    config()->set('seo.canonical_url', 'https://airgadget.ir');
    $category = Category::create(['name' => 'Test', 'slug' => 'test']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Product',
        'slug' => 'product',
        'sku' => 'SEO-1',
        'price' => 100,
        'stock' => 1,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product, false))
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/products/product">', false);

    $product->update(['canonical_url' => 'https://airgadget.ir/products/preferred-product']);

    $this->get(route('products.show', $product, false))
        ->assertSee('<link rel="canonical" href="https://airgadget.ir/products/preferred-product">', false);
});

test('legacy article tag path permanently redirects to the canonical tag path', function () {
    Tag::create(['name' => 'Guide', 'slug' => 'guide']);

    $this->get('/articles/tags/guide')
        ->assertRedirect('/tags/guide')
        ->assertStatus(301);
});

test('private and utility pages are noindex', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex,follow')
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});
