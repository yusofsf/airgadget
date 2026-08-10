<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function favoriteTestProduct(): Product
{
    $category = Category::create([
        'name' => 'لوازم جانبی',
        'slug' => 'favorite-accessories',
    ]);

    return Product::create([
        'category_id' => $category->id,
        'name' => 'هدفون محبوب',
        'slug' => 'favorite-headphone',
        'sku' => 'FAV-100',
        'price' => 750000,
        'stock' => 4,
        'is_active' => true,
    ]);
}

test('a user can persist and remove a favorite product', function () {
    $user = User::factory()->create();
    $product = favoriteTestProduct();

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $product))
        ->assertOk()
        ->assertJson([
            'product_id' => $product->id,
            'is_favorite' => true,
        ]);

    $this->assertDatabaseHas('favorite_product_user', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('favoriteProductIds', [$product->id])
        );

    $this->actingAs($user)
        ->get(route('account'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('favoriteProducts.data.0.id', $product->id)
        );

    $this->actingAs($user)
        ->postJson(route('favorites.toggle', $product))
        ->assertOk()
        ->assertJson(['is_favorite' => false]);

    $this->assertDatabaseMissing('favorite_product_user', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
});

test('guests cannot change favorites', function () {
    $product = favoriteTestProduct();

    $this->postJson(route('favorites.toggle', $product))
        ->assertUnauthorized();
});
