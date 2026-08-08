<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

function createShopProduct(Category $category, Brand $brand, int $number, array $overrides = []): Product
{
    return Product::create(array_merge([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => "Product {$number}",
        'slug' => "product-{$number}",
        'sku' => "SKU-{$number}",
        'price' => 100000 + $number,
        'sale_price' => null,
        'stock' => 0,
        'is_active' => true,
    ], $overrides));
}

test('shop paginates active products twelve at a time and exposes the filtered total', function () {
    $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);
    $brand = Brand::create(['name' => 'Airgadget', 'slug' => 'airgadget']);

    foreach (range(1, 14) as $number) {
        createShopProduct($category, $brand, $number);
    }

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->has('products.data', 12)
            ->where('products.current_page', 1)
            ->where('products.per_page', 12)
            ->where('products.last_page', 2)
            ->where('products.total', 14)
            ->where('filteredProductCount', 14)
        );

    $this->get(route('shop', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.current_page', 2)
            ->where('filteredProductCount', 14)
        );
});

test('shop total and pagination are calculated after all selected filters', function () {
    $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);
    $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other']);
    $brand = Brand::create(['name' => 'Airgadget', 'slug' => 'airgadget']);

    foreach (range(1, 13) as $number) {
        createShopProduct($category, $brand, $number, [
            'price' => 200000,
            'sale_price' => 150000,
            'stock' => 2,
        ]);
    }
    createShopProduct($category, $brand, 20, ['price' => 200000, 'sale_price' => null, 'stock' => 2]);
    createShopProduct($otherCategory, $brand, 21, ['price' => 200000, 'sale_price' => 150000, 'stock' => 2]);

    $filters = [
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'min_price' => 140000,
        'max_price' => 160000,
        'status' => 'sale',
    ];

    $this->get(route('shop', $filters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)
            ->where('products.last_page', 2)
            ->where('products.total', 13)
            ->where('filteredProductCount', 13)
            ->where('shopFilters.status', 'sale')
        );

    $this->get(route('shop', [...$filters, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.total', 13)
            ->where('filteredProductCount', 13)
        );
});
