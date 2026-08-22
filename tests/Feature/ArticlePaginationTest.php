<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Inertia\Testing\AssertableInertia as Assert;

test('magazine paginates published articles six at a time without top-level tags', function () {
    $category = ArticleCategory::create([
        'name' => 'راهنمای خرید',
        'slug' => 'buying-guides',
    ]);
    Tag::create(['name' => 'موبایل', 'slug' => 'mobile']);

    foreach (range(1, 12) as $number) {
        Article::create([
            'article_category_id' => $category->id,
            'title' => "مقاله {$number}",
            'slug' => "article-{$number}",
            'body' => 'متن مقاله',
            'is_published' => true,
            'published_at' => now()->subMinutes($number),
        ]);
    }

    $this->get(route('articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'articles')
            ->has('articles.data', 6)
            ->where('articles.current_page', 1)
            ->where('articles.per_page', 6)
            ->where('articles.last_page', 2)
            ->where('articles.total', 12)
            ->missing('tags')
        );

    $this->get(route('articles.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 6)
            ->where('articles.current_page', 2)
            ->where('articles.per_page', 6)
        );
});

test('topic pages use the same six-article pagination', function () {
    $category = ArticleCategory::create([
        'name' => 'بررسی تخصصی',
        'slug' => 'reviews',
    ]);

    foreach (range(1, 11) as $number) {
        Article::create([
            'article_category_id' => $category->id,
            'title' => "بررسی {$number}",
            'slug' => "review-{$number}",
            'body' => 'متن بررسی',
            'is_published' => true,
            'published_at' => now()->subMinutes($number),
        ]);
    }

    $this->get(route('article-categories.show', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedArticleCategory.id', $category->id)
            ->has('articles.data', 6)
            ->where('articles.per_page', 6)
            ->where('articles.last_page', 2)
            ->missing('tags')
        );
});
