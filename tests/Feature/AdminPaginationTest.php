<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin product and article lists paginate independently ten at a time', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = Category::create(['name' => 'Pagination', 'slug' => 'pagination']);

    foreach (range(1, 12) as $number) {
        Product::create([
            'category_id' => $category->id,
            'name' => "Product {$number}",
            'slug' => "admin-product-{$number}",
            'sku' => "ADMIN-PAGE-{$number}",
            'price' => 100000,
            'stock' => $number,
            'is_active' => true,
        ]);
        Article::create([
            'title' => "Article {$number}",
            'slug' => "admin-article-{$number}",
            'body' => 'Article body',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 10)
            ->where('products.per_page', 10)
            ->where('products.total', 12)
            ->has('articles.data', 10)
            ->where('articles.per_page', 10)
            ->where('articles.total', 12)
            ->where('adminProductStats.total', 12)
        );

    $this->actingAs($admin)
        ->get(route('admin', ['tab' => 'products', 'product_page' => 2, 'article_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminTab', 'products')
            ->where('products.current_page', 2)
            ->has('products.data', 2)
            ->where('articles.current_page', 2)
            ->has('articles.data', 2)
        );
});
