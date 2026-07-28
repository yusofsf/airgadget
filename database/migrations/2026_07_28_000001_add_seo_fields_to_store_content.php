<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'articles', 'categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('meta_title')->nullable()->after('slug');
                $table->text('meta_description')->nullable()->after('meta_title');
                $table->string('meta_keywords')->nullable()->after('meta_description');
                $table->string('canonical_url')->nullable()->after('meta_keywords');
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'articles', 'categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords', 'canonical_url']);
            });
        }
    }
};
