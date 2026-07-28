<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Support\PersianSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function update(Request $request, Article $article): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'category_name' => ['nullable', 'string', 'max:150'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['required', 'boolean'],
        ]);

        $validated['article_category_id'] = $this->resolveCategory($validated['category_name'] ?? '')?->id;
        $validated['published_at'] = $validated['is_published']
            ? ($article->published_at ?: now())
            : null;
        $article->update(collect($validated)->except(['tags', 'category_name'])->all());
        $article->tags()->sync($this->tagIds($validated['tags'] ?? ''));

        return back()->with('status', "مقاله «{$article->title}» ویرایش شد.");
    }

    public function destroy(Article $article): RedirectResponse
    {
        $title = $article->title;
        $image = $article->image ? Str::after($article->image, '/storage/') : null;
        $article->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return back()->with('status', "مقاله «{$title}» حذف شد.");
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'category_name' => ['nullable', 'string', 'max:150'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'main_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $article = Article::create([
            'article_category_id' => $this->resolveCategory($validated['category_name'] ?? '')?->id,
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'image' => $request->file('main_image')
                ? '/storage/'.$request->file('main_image')->store('articles', 'public')
                : null,
            'meta_title' => $validated['meta_title'] ?? $validated['title'],
            'meta_description' => $validated['meta_description'] ?? ($validated['excerpt'] ?? null),
            'is_published' => true,
            'published_at' => now(),
        ]);

        $article->tags()->sync($this->tagIds($validated['tags'] ?? ''));

        return redirect()
            ->route('admin')
            ->with('status', "مقاله «{$article->title}» با موفقیت ثبت شد.");
    }

    private function tagIds(string $tags): array
    {
        return collect(preg_split('/[,،]/u', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(function ($name) {
                return Tag::firstOrCreate(
                    ['name' => $name],
                    ['slug' => $this->uniqueTagSlug($name)]
                )->id;
            })
            ->values()
            ->all();
    }

    private function resolveCategory(string $name): ?ArticleCategory
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return ArticleCategory::firstOrCreate(
            ['name' => $name],
            ['slug' => $this->uniqueCategorySlug($name)]
        );
    }

    private function uniqueSlug(string $value): string
    {
        return PersianSlug::unique(Article::class, $value);
    }

    private function uniqueTagSlug(string $value): string
    {
        return PersianSlug::unique(Tag::class, $value);
    }

    private function uniqueCategorySlug(string $value): string
    {
        return PersianSlug::unique(ArticleCategory::class, $value);
    }
}
