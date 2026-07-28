<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Product $product): JsonResponse
    {
        $favorites = $request->user()->favoriteProducts();
        $isFavorite = $favorites->whereKey($product->id)->exists();

        if ($isFavorite) {
            $favorites->detach($product->id);
        } else {
            $favorites->attach($product->id);
        }

        return response()->json([
            'product_id' => $product->id,
            'is_favorite' => ! $isFavorite,
        ]);
    }
}
