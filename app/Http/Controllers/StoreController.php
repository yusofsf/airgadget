<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->with(['brand:id,name', 'images:id,product_id,path,sort_order'])
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$term}%"));
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'stock' => $product->stock,
                'brand' => $product->brand?->name,
                'image' => $product->main_image ?: $product->images->sortBy('sort_order')->first()?->path,
                'short_description' => $product->short_description,
            ]);

        return response()->json(['products' => $products]);
    }

    public function home()
    {
        return Inertia::render('Storefront', [
            'view' => 'home',
            'products' => Product::with(['brand', 'images', 'tags'])->where('is_active', true)->latest()->take(8)->get(),
            'categoryCounts' => Category::whereHas('products', fn ($query) => $query->where('is_active', true))
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function shop(Request $request)
    {
        return $this->shopResponse($request);
    }

    public function category(Request $request, Category $category)
    {
        return $this->shopResponse($request, $category);
    }

    private function shopResponse(Request $request, ?Category $selectedCategory = null)
    {
        $brandId = $request->integer('brand_id') ?: null;
        $categoryId = $request->integer('category_id') ?: $selectedCategory?->id;
        $minPrice = $request->filled('min_price') && is_numeric($request->input('min_price')) ? (float) $request->input('min_price') : null;
        $maxPrice = $request->filled('max_price') && is_numeric($request->input('max_price')) ? (float) $request->input('max_price') : null;
        $productsQuery = Product::with(['brand', 'images', 'tags'])->where('is_active', true)
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($minPrice !== null || $maxPrice !== null, function ($query) use ($minPrice, $maxPrice) {
                $query->where(function ($priceQuery) use ($minPrice, $maxPrice) {
                    $priceQuery->where(function ($saleQuery) use ($minPrice, $maxPrice) {
                        $saleQuery->whereNotNull('sale_price')
                            ->when($minPrice !== null, fn ($q) => $q->where('sale_price', '>=', $minPrice))
                            ->when($maxPrice !== null, fn ($q) => $q->where('sale_price', '<=', $maxPrice));
                    })->orWhere(function ($regularQuery) use ($minPrice, $maxPrice) {
                        $regularQuery->whereNull('sale_price')
                            ->when($minPrice !== null, fn ($q) => $q->where('price', '>=', $minPrice))
                            ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice));
                    });
                });
            });

        return Inertia::render('Storefront', [
            'view' => 'shop',
            'products' => $productsQuery->latest()->paginate(12)->withQueryString(),
            'selectedCategory' => $selectedCategory?->only(['id', 'name', 'slug']),
            'shopFilters' => [
                'brand_id' => $brandId ? (string) $brandId : '',
                'category_id' => $categoryId ? (string) $categoryId : '',
                'min_price' => $minPrice !== null ? (string) $minPrice : '',
                'max_price' => $maxPrice !== null ? (string) $maxPrice : '',
            ],
            'brandOptions' => Brand::whereHas('products', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(['id', 'name']),
            'categoryOptions' => Category::whereHas('products', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active, 404);

        return Inertia::render('Storefront', [
            'view' => 'product',
            'product' => $product->load(['brand', 'category', 'images', 'specifications', 'tags']),
        ]);
    }

    public function articles()
    {
        return Inertia::render('Storefront', [
            'view' => 'articles',
            'articles' => Article::with(['tags', 'category'])->where('is_published', true)->latest('published_at')->paginate(12),
            'articleCategories' => ArticleCategory::whereHas('articles', fn ($query) => $query->where('is_published', true))->orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::whereHas('articles', fn ($query) => $query->where('is_published', true))->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function articleCategory(ArticleCategory $articleCategory)
    {
        return Inertia::render('Storefront', [
            'view' => 'articles',
            'articles' => Article::with(['tags', 'category'])->where('is_published', true)->whereBelongsTo($articleCategory, 'category')->latest('published_at')->paginate(12),
            'articleCategories' => ArticleCategory::whereHas('articles', fn ($query) => $query->where('is_published', true))->orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::whereHas('articles', fn ($query) => $query->where('is_published', true))->orderBy('name')->get(['id', 'name', 'slug']),
            'selectedArticleCategory' => $articleCategory->only(['id', 'name', 'slug']),
        ]);
    }

    public function tag(Tag $tag)
    {
        return Inertia::render('Storefront', [
            'view' => 'tag',
            'selectedTag' => $tag->only(['id', 'name', 'slug']),
            'articles' => Article::with(['tags', 'category'])->where('is_published', true)
                ->whereHas('tags', fn ($query) => $query->whereKey($tag->id))
                ->latest('published_at')->get(),
            'products' => Product::with(['brand', 'images', 'tags'])->where('is_active', true)
                ->whereHas('tags', fn ($query) => $query->whereKey($tag->id))
                ->latest()->get(),
        ]);
    }

    public function article(Article $article)
    {
        abort_unless($article->is_published, 404);

        return Inertia::render('Storefront', [
            'view' => 'article',
            'article' => $article->load(['tags', 'category']),
        ]);
    }

    public function page(string $page)
    {
        return Inertia::render('Storefront', ['view' => $page]);
    }

    public function account()
    {
        $user = request()->user();
        $orders = $user && Schema::hasTable('orders') && Schema::hasTable('order_items')
            ? $user->orders()->with('items')->latest()->get()
            : collect();
        $favorites = $user && Schema::hasTable('favorite_product_user')
            ? $user->favoriteProducts()
                ->with(['brand', 'images'])
                ->where('is_active', true)
                ->latest('favorite_product_user.created_at')
                ->get()
            : collect();

        return Inertia::render('Storefront', [
            'view' => 'account',
            'orders' => $orders,
            'favoriteProducts' => $favorites,
            'completeProfile' => request()->boolean('complete_profile'),
        ]);
    }

    public function checkout()
    {
        if (! request()->user()->hasCompleteShippingAddress()) {
            return redirect()
                ->route('account', ['complete_profile' => 1])
                ->with('status', 'برای ثبت سفارش، ابتدا آدرس و کدپستی را در حساب کاربری وارد کنید.');
        }

        return Inertia::render('Storefront', [
            'view' => 'checkout',
            'shippingMethods' => collect(StoreSetting::shippingMethods())->where('is_active', true)->values(),
        ]);
    }

    public function admin()
    {
        $hasOrders = Schema::hasTable('orders') && Schema::hasTable('order_items');
        $accounting = ['received' => 0, 'product_revenue' => 0, 'shipping_revenue' => 0, 'sold_items' => 0, 'paid_orders' => 0, 'pending_orders' => 0];

        if ($hasOrders) {
            $paid = Order::query();
            if (Schema::hasColumn('orders', 'paid_at')) {
                $paid->where(fn ($query) => $query->whereNotNull('paid_at')->orWhereIn('status', ['processing', 'completed']));
            } else {
                $paid->whereIn('status', ['processing', 'completed']);
            }
            $soldItems = Schema::hasColumn('order_items', 'quantity')
                ? (int) (clone $paid)->withSum('items', 'quantity')->get()->sum('items_sum_quantity')
                : 0;
            $accounting = [
                'received' => (clone $paid)->sum('total'),
                'product_revenue' => (clone $paid)->selectRaw('COALESCE(SUM(subtotal - discount), 0) total')->value('total'),
                'shipping_revenue' => (clone $paid)->sum('shipping_cost'),
                'sold_items' => $soldItems,
                'paid_orders' => (clone $paid)->count(),
                'pending_orders' => Order::where('status', 'pending_payment')->count(),
            ];
        }

        return Inertia::render('Storefront', [
            'view' => 'admin',
            'products' => Product::with(['brand', 'category', 'images', 'tags'])->latest()->get(),
            'articles' => Article::with(['tags', 'category'])->latest()->get(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'tags' => Tag::withCount(['products', 'articles'])->orderBy('name')->get(),
            'articleCategories' => ArticleCategory::withCount('articles')->orderBy('name')->get(),
            'accounting' => $accounting,
            'shippingMethods' => StoreSetting::shippingMethods(),
        ]);
    }
}
