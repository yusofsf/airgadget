<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sources = [
            'categories' => 'name',
            'brands' => 'name',
            'products' => 'name',
            'tags' => 'name',
            'article_categories' => 'name',
            'articles' => 'title',
        ];

        foreach ($sources as $table => $sourceColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'slug')) {
                continue;
            }

            $records = DB::table($table)
                ->select(['id', $sourceColumn])
                ->orderBy('id')
                ->get();

            // Clear existing values with unique temporary slugs so conversion is
            // deterministic even when a later record already owns the target slug.
            foreach ($records as $record) {
                DB::table($table)->where('id', $record->id)->update([
                    'slug' => "slug-migration-{$table}-{$record->id}",
                ]);
            }

            foreach ($records as $record) {
                $base = $this->slugValue((string) $record->{$sourceColumn}) ?: 'بدون-نام';
                $slug = $base;
                $counter = 2;

                while (DB::table($table)->where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                DB::table($table)->where('id', $record->id)->update(['slug' => $slug]);
            }
        }
    }

    public function down(): void
    {
        // Previous transliterated slugs cannot be reconstructed reliably.
    }

    private function slugValue(string $value): string
    {
        $value = str_replace(
            ['ي', 'ى', 'ك'],
            ['ی', 'ی', 'ک'],
            trim(mb_strtolower($value))
        );
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?: '';

        return trim($value, '-');
    }
};
