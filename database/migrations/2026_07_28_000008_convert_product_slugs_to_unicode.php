<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->orderBy('id')->each(function (object $product): void {
            $base = $this->slugValue((string) $product->name) ?: 'product-'.$product->id;
            $slug = $base;
            $counter = 2;

            while (DB::table('products')->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $base.'-'.$counter;
                $counter++;
            }

            if ($product->slug !== $slug) {
                DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        // Original slugs cannot be reconstructed after conversion.
    }

    private function slugValue(string $value): string
    {
        $value = str_replace(['ي', 'ى', 'ك'], ['ی', 'ی', 'ک'], trim(mb_strtolower($value)));
        $value = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '-', $value) ?: '';

        return trim($value, '-');
    }
};
