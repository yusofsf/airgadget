<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home')],
            ['loc' => route('shop')],
            ['loc' => route('articles.index')],
            ['loc' => route('about')],
            ['loc' => route('contact')],
            ['loc' => route('terms')],
        ]);

        Category::query()
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->withMax(['products' => fn ($query) => $query->where('is_active', true)], 'updated_at')
            ->get()
            ->each(fn (Category $category) => $urls->push([
                'loc' => route('categories.show', $category),
                'lastmod' => $this->lastModified($category->products_max_updated_at ?: $category->updated_at),
            ]));

        Product::query()
            ->where('is_active', true)
            ->get(['slug', 'updated_at'])
            ->each(fn (Product $product) => $urls->push([
                'loc' => route('products.show', $product),
                'lastmod' => $this->lastModified($product->updated_at),
            ]));

        Article::query()
            ->where('is_published', true)
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(fn (Article $article) => $urls->push([
                'loc' => route('articles.show', $article),
                'lastmod' => $this->lastModified($article->updated_at ?: $article->published_at),
            ]));

        ArticleCategory::query()
            ->whereHas('articles', fn ($query) => $query->where('is_published', true))
            ->withMax(['articles' => fn ($query) => $query->where('is_published', true)], 'updated_at')
            ->get()
            ->each(fn (ArticleCategory $category) => $urls->push([
                'loc' => route('article-categories.show', $category),
                'lastmod' => $this->lastModified($category->articles_max_updated_at ?: $category->updated_at),
            ]));

        Tag::query()
            ->where(fn ($query) => $query
                ->whereHas('articles', fn ($articles) => $articles->where('is_published', true))
                ->orWhereHas('products', fn ($products) => $products->where('is_active', true)))
            ->withMax(['articles' => fn ($query) => $query->where('is_published', true)], 'updated_at')
            ->withMax(['products' => fn ($query) => $query->where('is_active', true)], 'updated_at')
            ->get()
            ->each(fn (Tag $tag) => $urls->push([
                'loc' => route('tags.show', $tag),
                'lastmod' => $this->lastModified(collect([
                    $tag->articles_max_updated_at,
                    $tag->products_max_updated_at,
                    $tag->updated_at,
                ])->filter()->max()),
            ]));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function lastModified($date): ?string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->toAtomString() : null;
    }
}
