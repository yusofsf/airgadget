<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Support\PersianSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
                ? $this->storeArticleImage($request->file('main_image'))
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

    private function storeArticleImage(UploadedFile $image): string
    {
        $root = (string) config('filesystems.disks.public.root');
        $articlesPath = $root.DIRECTORY_SEPARATOR.'articles';
        $writableTarget = is_dir($articlesPath) ? $articlesPath : $root;
        $context = [
            'original_name' => basename($image->getClientOriginalName()),
            'client_mime' => $image->getClientMimeType(),
            'size_bytes' => $image->getSize(),
            'upload_error' => $image->getError(),
            'is_valid' => $image->isValid(),
            'destination_root' => $articlesPath,
            'destination_exists' => is_dir($articlesPath),
            'writable_path_checked' => $writableTarget,
            'destination_writable' => is_writable($writableTarget),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'request_content_length' => request()->server('CONTENT_LENGTH'),
        ];
        $this->uploadLog('info', 'article_image.storage_started', $context);

        try {
            $disk = Storage::disk('public');
            $storedPath = $image->store('articles', 'public');

            if (! is_string($storedPath) || $storedPath === '' || ! $disk->exists($storedPath)) {
                throw new \RuntimeException('The uploaded article image could not be verified on disk.');
            }

            $this->uploadLog('info', 'article_image.storage_succeeded', [
                ...$context,
                'stored_path' => $storedPath,
                'absolute_path' => $disk->path($storedPath),
                'stored_size_bytes' => $disk->size($storedPath),
            ]);
        } catch (\Throwable $exception) {
            $this->uploadLog('error', 'article_image.storage_failed', [
                ...$context,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'last_php_error' => error_get_last(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'main_image' => 'ذخیره تصویر مقاله روی هاست انجام نشد؛ فایل لاگ فروشگاه را بررسی کنید.',
            ]);
        }

        return '/storage/'.$storedPath;
    }

    private function uploadLog(string $level, string $event, array $context): void
    {
        try {
            Log::channel(config('logging.store_channel', 'store'))->log($level, $event, [
                'request_id' => request()->attributes->get('store_request_id'),
                'user_id' => request()->user()?->getAuthIdentifier(),
                ...$context,
            ]);
        } catch (\Throwable $loggingException) {
            report($loggingException);
        }
    }
}
