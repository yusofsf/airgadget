<?php

namespace App\Support;

final class PersianSlug
{
    public static function make(string $value): string
    {
        $value = str_replace(
            ['ي', 'ى', 'ك'],
            ['ی', 'ی', 'ک'],
            trim(mb_strtolower($value))
        );
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?: '';

        return trim($value, '-');
    }

    public static function unique(string $model, string $value, ?int $ignoreId = null): string
    {
        $base = self::make($value) ?: 'بدون-نام';
        $slug = $base;
        $counter = 2;

        while ($model::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
