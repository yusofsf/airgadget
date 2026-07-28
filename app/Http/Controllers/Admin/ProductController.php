<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function storeImage(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_main' => ['nullable', 'boolean'],
        ]);

        $path = $this->storeProductImage($request->file('image'));
        $image = $product->images()->create([
            'path' => $path,
            'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
        ]);
        $gallery = collect($product->gallery ?? [])
            ->concat($product->images()->orderBy('sort_order')->pluck('path'))
            ->unique()
            ->values();

        $product->update([
            'main_image' => ($validated['is_main'] ?? false) || ! $product->main_image
                ? $path
                : $product->main_image,
            'gallery' => $gallery->all(),
        ]);

        return response()->json([
            'id' => $image->id,
            'path' => $image->path,
            'main_image' => $product->main_image,
        ], 201);
    }

    public function image(string $filename): BinaryFileResponse
    {
        abort_unless((bool) preg_match('/\A[a-zA-Z0-9._-]+\z/', $filename), 404);
        $path = "products/{$filename}";
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(
            Storage::disk('public')->path($path),
            ['Cache-Control' => 'public, max-age=31536000, immutable']
        );
    }

    public function show(Product $product): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-product',
            'adminProduct' => $product->load([
                'brand',
                'category',
                'tags',
                'images' => fn ($query) => $query->orderBy('sort_order'),
            ]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product->id)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
            'remove_legacy_paths' => ['nullable', 'array', 'max:20'],
            'remove_legacy_paths.*' => ['string', 'max:1000'],
            'main_image_choice' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($request, $validated, $product) {
            $relatedPaths = $product->images()->pluck('path');
            $legacyPaths = collect([$product->main_image, ...($product->gallery ?? [])])
                ->filter()
                ->unique()
                ->diff($relatedPaths)
                ->values();
            $removedLegacyPaths = $legacyPaths->intersect($validated['remove_legacy_paths'] ?? [])->values();

            $product->update(collect($validated)->only([
                'name', 'sku', 'category_id', 'brand_id', 'price', 'sale_price', 'stock',
                'short_description', 'description', 'meta_title', 'meta_description',
                'meta_keywords', 'weight', 'dimensions', 'is_active',
            ])->all());
            $product->tags()->sync($this->tagIds($validated['tags'] ?? ''));

            $removedImages = $product->images()
                ->whereIn('id', $validated['remove_image_ids'] ?? [])
                ->get();
            $product->images()->whereKey($removedImages->pluck('id'))->delete();
            Storage::disk('public')->delete(
                $removedImages->pluck('path')->map(fn ($path) => $this->storedImagePath($path))->filter()->all()
            );
            Storage::disk('public')->delete(
                $removedLegacyPaths->map(fn ($path) => $this->storedImagePath($path))->filter()->all()
            );

            $newImages = [];
            $nextSortOrder = (int) $product->images()->max('sort_order') + 1;
            foreach ($request->file('images', []) as $index => $image) {
                $path = $this->storeProductImage($image);
                $newImages[$index] = $product->images()->create([
                    'path' => $path,
                    'sort_order' => $nextSortOrder + $index,
                ]);
            }

            $images = $product->images()->orderBy('sort_order')->get();
            $remainingLegacyPaths = $legacyPaths->diff($removedLegacyPaths)->values();
            $choice = $validated['main_image_choice'] ?? null;
            $mainImage = null;
            if ($choice && Str::startsWith($choice, 'existing:')) {
                $mainImage = $images->firstWhere('id', (int) Str::after($choice, 'existing:'))?->path;
            } elseif ($choice && Str::startsWith($choice, 'new:')) {
                $mainImage = ($newImages[(int) Str::after($choice, 'new:')] ?? null)?->path;
            } elseif ($choice && Str::startsWith($choice, 'legacy:')) {
                $legacyIndex = (int) Str::after($choice, 'legacy:');
                $selectedLegacyPath = $legacyPaths->get($legacyIndex);
                $mainImage = $remainingLegacyPaths->contains($selectedLegacyPath) ? $selectedLegacyPath : null;
            }

            $allImagePaths = $remainingLegacyPaths->concat($images->pluck('path'))->unique()->values();
            if (! $mainImage && $allImagePaths->contains($product->main_image)) {
                $mainImage = $product->main_image;
            }

            $product->update([
                'main_image' => $mainImage ?: $allImagePaths->first(),
                'gallery' => $allImagePaths->all(),
            ]);
        });

        return back()->with('status', "محصول «{$product->name}» ویرایش شد.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $paths = $product->images()->pluck('path')
            ->push($product->main_image)
            ->filter()
            ->map(fn ($path) => $this->storedImagePath($path))
            ->filter()
            ->unique()
            ->all();
        $product->delete();
        Storage::disk('public')->delete($paths);

        return redirect()->route('admin')->with('status', "محصول «{$name}» حذف شد.");
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'main_image_index' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
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
                'sku' => ($validated['sku'] ?? null) ?: $this->uniqueSku($validated['name']),
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'meta_title' => $validated['meta_title'] ?? $validated['name'],
                'meta_description' => $validated['meta_description'] ?? ($validated['short_description'] ?? null),
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'price' => $validated['price'],
                'sale_price' => $validated['sale_price'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'is_active' => true,
            ]);
            $product->tags()->sync($this->tagIds($validated['tags'] ?? ''));

            $paths = [];
            foreach ($request->file('images', []) as $index => $image) {
                $path = $this->storeProductImage($image);
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
            ->with('status', "محصول «{$product->name}» با موفقیت ثبت شد.")
            ->with('created_product_id', $product->id);
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

    private function tagIds(string $tags): array
    {
        return collect(preg_split('/[,،]/u', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(
                ['name' => $name],
                ['slug' => $this->uniqueSlug(Tag::class, $name)]
            )->id)
            ->values()
            ->all();
    }

    private function uniqueSlug(string $model, string $value): string
    {
        $base = $this->slugValue($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function slugValue(string $value): string
    {
        $value = str_replace(['ي', 'ى', 'ك'], ['ی', 'ی', 'ک'], trim(Str::lower($value)));
        $value = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '-', $value) ?: '';

        return trim($value, '-');
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

    private function storedImagePath(string $url): ?string
    {
        if (Str::contains($url, '/product-images/')) {
            return 'products/'.basename($url);
        }

        if (Str::contains($url, '/storage/products/')) {
            return 'products/'.Str::after($url, '/storage/products/');
        }

        return null;
    }

    private function storeProductImage(UploadedFile $image): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('products') && ! $disk->makeDirectory('products')) {
            throw ValidationException::withMessages([
                'images' => 'پوشه تصاویر محصول روی سرور قابل نوشتن نیست.',
            ]);
        }

        $storedPath = $image->store('products', 'public');
        if (! is_string($storedPath) || $storedPath === '') {
            throw ValidationException::withMessages([
                'images' => 'ذخیره تصویر روی سرور انجام نشد؛ دسترسی پوشه storage را بررسی کنید.',
            ]);
        }

        return '/product-images/'.basename($storedPath);
    }
}
