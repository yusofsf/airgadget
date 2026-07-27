<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = collect(['uuid', 'first_name', 'last_name', 'phone_number'])
            ->reject(fn (string $column) => Schema::hasColumn('users', $column));

        if ($missing->isEmpty()) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($missing) {
            if ($missing->contains('uuid')) {
                $table->uuid('uuid')->nullable();
            }
            if ($missing->contains('first_name')) {
                $table->string('first_name')->nullable();
            }
            if ($missing->contains('last_name')) {
                $table->string('last_name')->nullable();
            }
            if ($missing->contains('phone_number')) {
                $table->string('phone_number')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Compatibility columns are intentionally preserved on rollback so
        // existing user data is never removed from legacy installations.
    }
};
