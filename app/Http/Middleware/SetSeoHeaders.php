<?php

namespace App\Http\Middleware;

use App\Models\Article;
use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSeoHeaders
{
    private const INDEXABLE_ROUTES = [
        'home', 'shop', 'categories.show', 'products.show', 'articles.index',
        'tags.show', 'article-categories.show', 'articles.show', 'about',
        'contact', 'terms',
    ];

    private const FILTER_PARAMETERS = ['brand_id', 'category_id', 'min_price', 'max_price'];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $isIndexable = $request->isMethod('GET') && in_array($routeName, self::INDEXABLE_ROUTES, true);
        $hasFilters = collect(self::FILTER_PARAMETERS)->contains(
            fn (string $key) => $request->query->has($key)
        );
        $robots = $isIndexable && ! $hasFilters ? 'index,follow' : 'noindex,follow';
        $canonical = $isIndexable ? $this->canonicalUrl($request, $routeName) : null;

        $request->attributes->set('seo.canonical', $canonical);
        $request->attributes->set('seo.robots', $robots);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', $robots);

        return $response;
    }

    private function canonicalUrl(Request $request, string $routeName): string
    {
        $customCanonical = match ($routeName) {
            'products.show' => $request->route('product') instanceof Product
                ? $request->route('product')->canonical_url : null,
            'articles.show' => $request->route('article') instanceof Article
                ? $request->route('article')->canonical_url : null,
            default => null,
        };

        if (is_string($customCanonical) && filter_var($customCanonical, FILTER_VALIDATE_URL)) {
            return $customCanonical;
        }

        $path = route($routeName, $request->route()->parameters(), false);
        $canonical = config('seo.canonical_url').'/'.ltrim($path, '/');
        $page = $request->integer('page');

        if ($page > 1 && in_array($routeName, ['shop', 'articles.index', 'article-categories.show'], true)) {
            $canonical .= '?page='.$page;
        }

        return $canonical;
    }
}
