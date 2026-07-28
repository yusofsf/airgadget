<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Inertia\Testing\AssertableInertia as Assert;

test('dynamic sitemap contains public categories products articles and tags', function () {
    $category = Category::create(['name' => 'هدفون', 'slug' => 'headphones']);
    $activeProduct = Product::create([
        'category_id' => $category->id,
        'name' => 'هدفون فعال',
        'slug' => 'active-headphone',
        'sku' => 'ACTIVE-1',
        'price' => 1000000,
        'stock' => 2,
        'is_active' => true,
    ]);
    $hiddenProduct = Product::create([
        'category_id' => $category->id,
        'name' => 'هدفون مخفی',
        'slug' => 'hidden-headphone',
        'sku' => 'HIDDEN-1',
        'price' => 1000000,
        'stock' => 0,
        'is_active' => false,
    ]);

    $tag = Tag::create(['name' => 'راهنمای خرید', 'slug' => 'buying-guide']);
    $articleCategory = ArticleCategory::create(['name' => 'راهنمای خرید', 'slug' => 'buying-guides']);
    $publishedArticle = Article::create([
        'article_category_id' => $articleCategory->id,
        'title' => 'مقاله منتشرشده',
        'slug' => 'published-article',
        'body' => 'متن مقاله',
        'is_published' => true,
        'published_at' => now(),
    ]);
    $publishedArticle->tags()->attach($tag);
    $activeProduct->tags()->attach($tag);

    $hiddenTag = Tag::create(['name' => 'مخفی', 'slug' => 'hidden-tag']);
    $draftArticle = Article::create([
        'title' => 'پیش‌نویس',
        'slug' => 'draft-article',
        'body' => 'متن پیش‌نویس',
        'is_published' => false,
    ]);
    $draftArticle->tags()->attach($hiddenTag);

    $response = $this->get(route('sitemap'))->assertOk();
    $content = $response->getContent();

    expect($response->headers->get('content-type'))->toContain('application/xml')
        ->and(simplexml_load_string($content))->not->toBeFalse()
        ->and($content)->toContain(route('categories.show', $category))
        ->and($content)->toContain(route('products.show', $activeProduct))
        ->and($content)->toContain(route('articles.show', $publishedArticle))
        ->and($content)->toContain(route('article-categories.show', $articleCategory))
        ->and($content)->toContain(route('tags.show', $tag))
        ->and($content)->not->toContain(route('products.show', $hiddenProduct))
        ->and($content)->not->toContain(route('articles.show', $draftArticle))
        ->and($content)->not->toContain(route('tags.show', $hiddenTag));
});

test('category and tag sitemap urls are real indexable pages', function () {
    $category = Category::create(['name' => 'کابل', 'slug' => 'cables']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'کابل فعال',
        'slug' => 'active-cable',
        'sku' => 'CABLE-1',
        'price' => 200000,
        'stock' => 4,
        'is_active' => true,
    ]);
    $tag = Tag::create(['name' => 'آموزش', 'slug' => 'tutorial']);
    $articleCategory = ArticleCategory::create(['name' => 'آموزش', 'slug' => 'training']);
    $article = Article::create([
        'article_category_id' => $articleCategory->id,
        'title' => 'مقاله آموزشی',
        'slug' => 'tutorial-article',
        'body' => 'متن مقاله',
        'is_published' => true,
        'published_at' => now(),
    ]);
    $article->tags()->attach($tag);
    $product->tags()->attach($tag);

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'shop')
            ->where('selectedCategory.slug', 'cables')
            ->where('products.data.0.id', $product->id)
        );

    $this->get(route('tags.show', $tag))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'tag')
            ->where('selectedTag.slug', 'tutorial')
            ->where('articles.0.id', $article->id)
            ->where('products.0.id', $product->id)
        );

    $this->get(route('article-categories.show', $articleCategory))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'articles')
            ->where('selectedArticleCategory.slug', 'training')
            ->where('articles.data.0.id', $article->id)
        );
});
