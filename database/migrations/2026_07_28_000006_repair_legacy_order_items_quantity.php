<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items') || Schema::hasColumn('order_items', 'quantity')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('price');
        });

        foreach (['qty', 'count'] as $legacyColumn) {
            if (Schema::hasColumn('order_items', $legacyColumn)) {
                DB::table('order_items')->update([
                    'quantity' => DB::raw($legacyColumn),
                ]);

                break;
            }
        }
    }

    public function down(): void
    {
        // This compatibility column is intentionally preserved on rollback.
    }
};
