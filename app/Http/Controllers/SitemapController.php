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
    private const MINIMUM_TAG_ITEMS_FOR_SITEMAP = 2;

    public function __invoke(): Response
    {
        $urls = collect([
            $this->sitemapUrl(route('home'), changefreq: 'hourly', priority: '1.0'),
            $this->sitemapUrl(route('shop')),
            $this->sitemapUrl(route('articles.index')),
            $this->sitemapUrl(route('about')),
        ]);

        Category::query()
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->withMax(['products' => fn ($query) => $query->where('is_active', true)], 'updated_at')
            ->get()
            ->each(fn (Category $category) => $urls->push($this->sitemapUrl(
                route('categories.show', $category),
                $category->products_max_updated_at ?: $category->updated_at,
            )));

        Product::query()
            ->where('is_active', true)
            ->get(['slug', 'updated_at'])
            ->each(fn (Product $product) => $urls->push($this->sitemapUrl(
                route('products.show', $product),
                $product->updated_at,
            )));

        Article::query()
            ->where('is_published', true)
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(fn (Article $article) => $urls->push($this->sitemapUrl(
                route('articles.show', $article),
                $article->updated_at ?: $article->published_at,
            )));

        ArticleCategory::query()
            ->whereHas('articles', fn ($query) => $query->where('is_published', true))
            ->withMax(['articles' => fn ($query) => $query->where('is_published', true)], 'updated_at')
            ->get()
            ->each(fn (ArticleCategory $category) => $urls->push($this->sitemapUrl(
                route('article-categories.show', $category),
                $category->articles_max_updated_at ?: $category->updated_at,
            )));

        Tag::query()
            ->where(fn ($query) => $query
                ->whereHas(
                    'articles',
                    fn ($articles) => $articles->where('is_published', true),
                    '>=',
                    self::MINIMUM_TAG_ITEMS_FOR_SITEMAP,
                )
                ->orWhereHas(
                    'products',
                    fn ($products) => $products->where('is_active', true),
                    '>=',
                    self::MINIMUM_TAG_ITEMS_FOR_SITEMAP,
                )
                ->orWhere(fn ($mixedContent) => $mixedContent
                    ->whereHas('articles', fn ($articles) => $articles->where('is_published', true))
                    ->whereHas('products', fn ($products) => $products->where('is_active', true))))
            ->withMax(['articles' => fn ($query) => $query->where('is_published', true)], 'updated_at')
            ->withMax(['products' => fn ($query) => $query->where('is_active', true)], 'updated_at')
            ->get()
            ->each(fn (Tag $tag) => $urls->push($this->sitemapUrl(
                route('tags.show', $tag),
                collect([
                    $tag->articles_max_updated_at,
                    $tag->products_max_updated_at,
                    $tag->updated_at,
                ])->filter()->max(),
            )));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function lastModified($date): ?string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->toAtomString() : null;
    }

    private function sitemapUrl(
        string $location,
        $lastModified = null,
        string $changefreq = 'monthly',
        string $priority = '0.64',
    ): array {
        return [
            'loc' => $location,
            'lastmod' => $this->lastModified($lastModified),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}
