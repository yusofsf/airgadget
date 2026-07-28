<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaxonomyController extends Controller
{
    public function updateTag(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')->ignore($tag->id)],
        ]);

        $tag->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug(Tag::class, $validated['name'], $tag->id),
        ]);

        return back()->with('status', "تگ «{$tag->name}» ویرایش شد.");
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->delete();

        return back()->with('status', "تگ «{$name}» حذف شد.");
    }

    public function updateArticleCategory(Request $request, ArticleCategory $articleCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('article_categories', 'name')->ignore($articleCategory->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $articleCategory->update([
            ...$validated,
            'slug' => $this->uniqueSlug(ArticleCategory::class, $validated['name'], $articleCategory->id),
        ]);

        return back()->with('status', "دسته‌بندی «{$articleCategory->name}» ویرایش شد.");
    }

    public function destroyArticleCategory(ArticleCategory $articleCategory): RedirectResponse
    {
        $name = $articleCategory->name;
        $articleCategory->delete();

        return back()->with('status', "دسته‌بندی «{$name}» حذف شد؛ مقاله‌های آن بدون دسته باقی ماندند.");
    }

    private function uniqueSlug(string $model, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($model::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
