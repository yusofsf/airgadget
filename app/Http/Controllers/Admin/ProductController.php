<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'main_image_index' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $product = DB::transaction(function () use ($request, $validated) {
            $category = $this->resolveCategory($validated);
            $brand = $this->resolveBrand($validated);
            $slug = $this->uniqueSlug(Product::class, $validated['name']);

            $product = Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand?->id,
                'name' => $validated['name'],
                'slug' => $slug,
                'sku' => $validated['sku'] ?: $this->uniqueSku($validated['name']),
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'meta_title' => $validated['meta_title'] ?? $validated['name'],
                'meta_description' => $validated['meta_description'] ?? ($validated['short_description'] ?? null),
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'price' => $validated['price'] ?? 0,
                'sale_price' => $validated['sale_price'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'is_active' => true,
            ]);

            $paths = [];
            foreach ($request->file('images', []) as $index => $image) {
                $path = '/storage/'.$image->store('products', 'public');
                $paths[$index] = $path;

                $product->images()->create([
                    'path' => $path,
                    'sort_order' => $index,
                ]);
            }

            if ($paths !== []) {
                $mainIndex = (int) ($validated['main_image_index'] ?? 0);
                $product->update([
                    'main_image' => $paths[$mainIndex] ?? reset($paths),
                    'gallery' => array_values($paths),
                ]);
            }

            return $product;
        });

        return redirect()
            ->route('admin')
            ->with('status', "محصول «{$product->name}» با موفقیت ثبت شد.");
    }

    private function resolveCategory(array $data): Category
    {
        if (! empty($data['category_id'])) {
            return Category::findOrFail($data['category_id']);
        }

        $name = trim((string) ($data['category_name'] ?? 'عمومی'));

        return Category::firstOrCreate(
            ['name' => $name],
            ['slug' => $this->uniqueSlug(Category::class, $name)]
        );
    }

    private function resolveBrand(array $data): ?Brand
    {
        if (! empty($data['brand_id'])) {
            return Brand::findOrFail($data['brand_id']);
        }

        $name = trim((string) ($data['brand_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return Brand::firstOrCreate(
            ['name' => $name],
            ['slug' => $this->uniqueSlug(Brand::class, $name)]
        );
    }

    private function uniqueSlug(string $model, string $value): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function uniqueSku(string $value): string
    {
        $prefix = strtoupper(Str::slug($value, ''));
        $prefix = $prefix !== '' ? substr($prefix, 0, 10) : 'AG';
        $sku = $prefix.'-'.now()->format('ymdHis');
        $counter = 2;

        while (Product::where('sku', $sku)->exists()) {
            $sku = "{$prefix}-".now()->format('ymdHis')."-{$counter}";
            $counter++;
        }

        return $sku;
    }
}
